<?php
declare(strict_types=1);

namespace GuzabaPlatform\Roles\Controllers;


use Azonmedia\Reflection\ReflectionMethod;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\NotImplementedException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Http\Method;
use Guzaba2\Kernel\Exceptions\ConfigurationException;
use Guzaba2\Orm\Exceptions\MultipleValidationFailedException;
use GuzabaPlatform\Platform\Application\BaseController;
use Guzaba2\Translator\Translator as t;
use Psr\Http\Message\ResponseInterface;
use ReflectionException;

/**
 * Class Role
 * @package GuzabaPlatform\Roles\Controllers
 * Provides role management
 */
class Role extends BaseController
{
    protected const CONFIG_DEFAULTS = [
        'routes' => [
            '/admin/roles/role'             => [
                Method::HTTP_POST => [self::class, 'create']
            ],
            '/admin/roles/role/{uuid}'      => [
                Method::HTTP_GET => [self::class, 'view'],
                Method::HTTP_PUT => [self::class, 'update'],
                Method::HTTP_DELETE => [self::class, 'remove'],//the ActiveRecordDefaultController could be used as well but for completeness of the API this is also provided
            ],

            '/admin/roles/role/{uuid}/role/{role_uuid}'      => [ //not used by the UI but still useful API methods
                Method::HTTP_POST => [self::class, 'grant_role'],
                Method::HTTP_DELETE => [self::class, 'revoke_role'],
            ],
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * These are the properties to be presented in the UI (as more are actually returned)
     */
    public const RECORD_PROPERTIES = [
        'role_id',
        'role_name',
        'role_description',
        //'role_is_user',//on the roles listing there is no need to show this - all roles shown there are non-user roles and this is non editable
        'meta_object_uuid',
        'granted_roles_uuids',
    ];

    /**
     * The editable properties form the UI. Must be a subset of @see self::RECORD_PROPERTIES
     */
    public const EDITABLE_RECORD_PROPERTIES = [
        'role_name',
        'role_description',
        'granted_roles_uuids',
    ];

    /**
     * @param string $uuid
     * @return ResponseInterface
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws RunTimeException
     * @throws ConfigurationException
     * @throws ReflectionException
     */
    public function view(string $uuid): ResponseInterface
    {
        $Role = new \GuzabaPlatform\Platform\Authentication\Models\Role($uuid);
        $struct = [];
        $struct['record_properties'] = self::RECORD_PROPERTIES;
        $struct['editable_record_properties'] = self::EDITABLE_RECORD_PROPERTIES;
        $struct['inherited_roles'] = $Role->get_inherited_roles_names_and_uuids();//only directly inherited roles
        $struct = [$struct, ...$Role->get_record_data()];
        return self::get_structured_ok_response($struct);
    }

    /**
     * The role_is_user can not be set or changed. It is set automatically on user creation.
     * @param string $role_name
     * @param array $granted_roles_uuids
     * @return ResponseInterface
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws MultipleValidationFailedException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function create(string $role_name, string $role_description, array $granted_roles_uuids): ResponseInterface
    {
        //$role_properties = func_get_args();
        $role_properties = (new ReflectionMethod(__CLASS__, __FUNCTION__))->getArgumentsAsArray(func_get_args());
        unset($role_properties['granted_roles_uuids']);
        $Role = \GuzabaPlatform\Roles\Models\Roles::create($role_properties, $granted_roles_uuids);
        return self::get_structured_ok_response( ['message' => sprintf(t::_('The role %1$s was created with UUID %2$s.'), $Role->role_name, $Role->get_uuid() )] );
    }

    /**
     * @param string $uuid
     * @param string $role_name
     * @param array $granted_roles_uuids
     * @return ResponseInterface
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws MultipleValidationFailedException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function update(string $uuid, string $role_name, string $role_description, array $granted_roles_uuids): ResponseInterface
    {
        $role_properties = (new ReflectionMethod(__CLASS__, __FUNCTION__))->getArgumentsAsArray(func_get_args());
        unset($role_properties['granted_roles_uuids'], $role_properties['uuid']);

        $Role = new \Guzaba2\Authorization\Role($uuid);
        \GuzabaPlatform\Roles\Models\Roles::update($Role, $role_properties, $granted_roles_uuids);
        return self::get_structured_ok_response( ['message' => sprintf(t::_('The role %1$s with UUID %2$s was updated.'), $Role->role_name, $Role->get_uuid() )] );
    }

    /**
     * @return ResponseInterface
     * @throws NotImplementedException
     * @throws ReflectionException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function remove(): ResponseInterface
    {
        //throw new NotImplementedException(sprintf(t::_('Deleting users is not allowed. Please use %1$s() (route: %2$s).'), __CLASS__.'::disable', '/admin/users/user/{uuid}/disable' ));
        //TODO implement
    }

    /**
     * @param string $uuid Receiver role
     * @param string $role_uuid Role to be granted
     * @return ResponseInterface
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function grant_role(string $uuid, string $role_uuid): ResponseInterface
    {
        $Role = new \Guzaba2\Authorization\Role($uuid);
        $RoleToGrant = new \Guzaba2\Authorization\Role($role_uuid);
        $Role->grant_role($RoleToGrant);
        return self::get_structured_ok_response( ['message' => sprintf(t::_('The role %1$s was granted role %2$s.'), $Role->role_name, $RoleToGrant->role_name )] );
    }

    /**
     * @param string $uuid
     * @param string $role_uuid
     * @return ResponseInterface
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function revoke_role(string $uuid, string $role_uuid): ResponseInterface
    {
        $Role = new \Guzaba2\Authorization\Role($uuid);
        $RoleToRevoke = new \Guzaba2\Authorization\Role($role_uuid);
        $User->revoke_role($Role);
        return self::get_structured_ok_response( ['message' => sprintf(t::_('The role %1$s was revoked role %2$s.'), $Role->role_name, $RoleToRevoke->role_name )] );
    }


}