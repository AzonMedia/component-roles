<?php
declare(strict_types=1);

namespace GuzabaPlatform\Roles\Models;

use Azonmedia\Exceptions\InvalidArgumentException;
use Guzaba2\Authorization\Role;
use Guzaba2\Authorization\RolesHierarchy;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Kernel\Exceptions\ConfigurationException;
use Guzaba2\Orm\Exceptions\MultipleValidationFailedException;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Translator\Translator as t;
use GuzabaPlatform\Platform\Application\MysqlConnectionCoroutine;


class Roles extends Base
{
    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'ConnectionFactory',
            'MysqlOrmStore',//needed because the get_class_id() method is used
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    public const SEARCH_CRITERIA = [
        'role_uuid',
        'meta_object_uuid',//the same as role_uuid
        'role_id',
        'role_name',
        'inherits_role_uuid',//check for the given role in the whole inheritance tree
        'inherits_role_name',
        'granted_role_uuid',//check for the given role only in the immediately granted roles
        'granted_role_name',
    ];

    /**
     * @param array $role_properties
     * @param array $granted_roles_uuids
     * @return Role
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws MultipleValidationFailedException
     * @throws RunTimeException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public static function create(array $role_properties, array $granted_roles_uuids): Role
    {
        $Role = new Role();
        self::update($Role, $role_properties, $granted_roles_uuids);
        return $Role;
    }

    /**
     * @param Role $Role
     * @param array $role_properties
     * @param array $granted_roles_uuids
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws MultipleValidationFailedException
     * @throws RunTimeException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public static function update(Role $Role, array $role_properties, array $granted_roles_uuids): void
    {
        //a transaction is started as the role creation/update and role granting needs to be one operation.
        $Transaction = ActiveRecord::new_transaction($TR);
        $Transaction->begin();

        foreach ($role_properties as $property_name=>$property_value) {
            $Role->{$property_name} = $property_value;
        }
        $Role->write();

        $current_granted_roles_uuids = $Role->get_role()->get_inherited_roles_uuids();
        foreach ($current_granted_roles_uuids as $current_inherited_role_uuid) {
            if (!in_array($current_inherited_role_uuid, $granted_roles_uuids)) {
                $RoleToRevoke = new Role($current_inherited_role_uuid);
                $Role->revoke_role($RoleToRevoke);
            }
        }

        foreach ($granted_roles_uuids as $inherited_role_uuid) {
            if (!in_array($inherited_role_uuid, $current_granted_roles_uuids)) {
                $RoleToGrant = new Role($inherited_role_uuid);
                $Role->grant_role($RoleToGrant);
            }
        }

        $Transaction->commit();

    }

    /**
     * Returns roles based on the provided $search_criteria. Please @see self::SEARCH_CRITERIA for the valid keys.
     * Returns only non-user roles.
     * @param array $search_criteria
     * @param string $order_by
     * @param string $order
     * @param int $offset
     * @param int $limit
     * @param int|null $total_found_rows
     * @return iterable
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws LogicException
     * @throws ConfigurationException
     * @throws \ReflectionException
     */
    public static function get_roles(array $search_criteria, int $offset = 0, int $limit = 0,  string $order_by = 'role_name', string $order = 'ASC', ?int &$total_found_rows = NULL): iterable
    {

        foreach ($search_criteria as $key=>$value) {
            if (!in_array($key, self::SEARCH_CRITERIA)) {
                throw new \Guzaba2\Base\Exceptions\InvalidArgumentException(sprintf(t::_('The $search_criteria contains an unsupported key %1$s. The supported keys are %2$s.'), $key, implode(',', self::SEARCH_CRITERIA) ));
            }
        }

        /** @var ConnectionInterface $Connection */
        $Connection = self::get_service('ConnectionFactory')->get_connection(MysqlConnectionCoroutine::class, $CR);

        $roles_hierarchy_table = RolesHierarchy::get_main_table();
        $roles_table = Role::get_main_table();
        /** @var Mysql $MysqlOrmStore */
        $MysqlOrmStore = self::get_service('MysqlOrmStore');
        $meta_table = $MysqlOrmStore::get_meta_table();

        $w = $b = [];

        if (array_key_exists('role_id', $search_criteria) && $search_criteria['role_id'] !== NULL) {
            $w[] = "roles.role_id = :role_id";
            $b['role_id'] = (int) $search_criteria['role_id'];
        }
        if (array_key_exists('role_uuid', $search_criteria) && $search_criteria['role_uuid'] !== NULL) {
            $w[] = "meta.meta_object_uuid LIKE :role_uuid";
            $b['role_uuid'] = '%'.$search_criteria['role_uuid'].'%';
        }
        if (array_key_exists('meta_object_uuid', $search_criteria) && $search_criteria['meta_object_uuid'] !== NULL) {
            $w[] = "meta.meta_object_uuid LIKE :role_uuid";
            $b['role_uuid'] = '%'.$search_criteria['meta_object_uuid'].'%';
        }
        if (array_key_exists('role_name', $search_criteria) && $search_criteria['role_name'] !== NULL) {
            $w[] = "roles.role_name LIKE :role_name";
            $b['role_name'] = '%'.$search_criteria['role_name'].'%';
        }
        if (array_key_exists('role_description', $search_criteria) && $search_criteria['role_description'] !== NULL) {
            $w[] = "roles.role_description LIKE :role_description";
            $b['role_description'] = '%'.$search_criteria['role_description'].'%';
        }

        //always return onle system (not users) roles
        $w[] = 'roles.role_is_user = 0';

        if (array_key_exists('inherits_role_uuid', $search_criteria) && $search_criteria['inherits_role_uuid'] !== NULL) {
            try {
                $Role = new Role($search_criteria['inherits_role_uuid']);
            } catch (RecordNotFoundException $Exception) {
                throw new \Guzaba2\Base\Exceptions\InvalidArgumentException(sprintf(t::_('There is no role with UUID %1$s as provided in "%2$s" key in $search_criteria.'), $search_criteria['inherits_role_uuid'], 'inherits_role_uuid' ));
            }
            $inheriting_roles_ids = $Role->get_all_inheriting_roles_ids();//already includes this role id
            $ids_placeholder = $Connection::array_placeholder($inheriting_roles_ids, 'role');
            $w[] = "roles_hierarchy.inherited_role_id IN ({$ids_placeholder})";
            $b['role'] = $inheriting_roles_ids;
        }
        if (array_key_exists('inherits_role_name', $search_criteria) && $search_criteria['inherits_role_name'] !== NULL) {
            try {
                $Role = new Role( ['role_name' => $search_criteria['inherits_role_name'] ]);//role_name is unique
            } catch (RecordNotFoundException $Exception) {
                throw new \Guzaba2\Base\Exceptions\InvalidArgumentException(sprintf(t::_('There is no role with role_name %1$s as provided in "%2$s" key in $search_criteria.'), $search_criteria['inherits_role_name'], 'inherits_role_name' ));
            }
            $inheriting_roles_ids = $Role->get_all_inheriting_roles_ids();//already includes this role id
            $ids_placeholder = $Connection::array_placeholder($inheriting_roles_ids, 'role');
            $w[] = "roles_hierarchy.inherited_role_id IN ({$ids_placeholder})";
            $b['role'] = $inheriting_roles_ids;
        }

        if ($w) {
            $w_str = "WHERE".PHP_EOL.implode(PHP_EOL."AND ", $w);
        } else {
            $w_str = "";
        }
        if ($offset || $limit) {
            $l_str = "LIMIT {$offset}, {$limit}";
        } else {
            $l_str = "";
        }

        $b['meta_class_id'] = $MysqlOrmStore->get_class_id(Role::class);

        $q = "
SELECT
    roles.role_id, roles.role_name, roles.role_is_user, roles.role_description,
    meta.meta_object_uuid, meta.meta_class_id, meta.meta_object_create_microtime, meta.meta_object_last_update_microtime,
    meta.meta_object_create_role_id, meta.meta_object_last_update_role_id,
    GROUP_CONCAT(roles_hierarchy.inherited_role_id SEPARATOR ',') AS granted_roles_ids,
    GROUP_CONCAT(inherited_roles.role_name SEPARATOR ',') AS granted_roles_names,
    GROUP_CONCAT(inherited_roles_meta.meta_object_uuid SEPARATOR ',') AS granted_roles_uuids
FROM
    {$Connection::get_tprefix()}{$roles_table} AS roles
    INNER JOIN {$Connection::get_tprefix()}{$meta_table} AS meta ON meta.meta_object_id = roles.role_id AND meta.meta_class_id = :meta_class_id
    LEFT JOIN {$Connection::get_tprefix()}{$roles_hierarchy_table} AS roles_hierarchy ON roles_hierarchy.role_id = roles.role_id
    LEFT JOIN {$Connection::get_tprefix()}{$roles_table} AS inherited_roles ON inherited_roles.role_id = roles_hierarchy.inherited_role_id
    LEFT JOIN {$Connection::get_tprefix()}{$meta_table} AS inherited_roles_meta ON inherited_roles_meta.meta_object_id = inherited_roles.role_id AND inherited_roles_meta.meta_class_id = meta.meta_class_id
{$w_str}
GROUP BY
    roles.role_id
{$l_str}
        ";

        //because the inherited role filter is applied after the query is executed there is no point having two parallel queries (one for data and one for total count if there is limit provided)

        $data = $Connection->prepare($q)->execute($b)->fetchAll();
        print_r($data);
        $total_found_rows = count($data);
        for ($aa = 0; $aa < $total_found_rows; $aa++) {
            if (!$data[$aa]['granted_roles_ids']) {
                $data[$aa]['granted_roles_ids'] = [];
            } else {
                $data[$aa]['granted_roles_ids'] = explode(',', $data[$aa]['granted_roles_ids']);
            }
            if (!$data[$aa]['granted_roles_names']) {
                $data[$aa]['granted_roles_names'] = [];
            } else {
                $data[$aa]['granted_roles_names'] = explode(',', $data[$aa]['granted_roles_names']);
            }
            if (!$data[$aa]['granted_roles_uuids']) {
                $data[$aa]['granted_roles_uuids'] = [];
            } else {
                $data[$aa]['granted_roles_uuids'] = explode(',', $data[$aa]['granted_roles_uuids']);
            }

            $data[$aa]['role_is_user'] = (bool) $data[$aa]['role_is_user'];
        }

        return $data;

    }
}