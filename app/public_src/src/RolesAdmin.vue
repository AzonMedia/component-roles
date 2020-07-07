<template>
    <div class="crud">
        <div class="content">
            <div id="data" class="tab">
                <h3>Roles <b-button variant="success" @click="show_update_modal('post', newObject)" size="sm">Create New</b-button> </h3>

                <!-- ======================= USERS LISTING ================== -->
                <template>
                    <b-form @submit="submitSearch">
                        <b-table striped show-empty :items="items" :fields="fields" empty-text="No records found!" @row-clicked="row_click_handler" no-local-sorting @sort-changed="sortingChanged" head-variant="dark" table-hover>

                            <template slot="top-row" slot-scope="{ fields }">
                                <td v-for="field in fields">
                                    <!-- <template v-if="field.key=='meta_object_uuid'"> -->
                                    <template v-if="field.key=='action'">
                                        <b-button size="sm" variant="outline-primary" type="submit" @click="search()">Search</b-button>
                                    </template>
                                    <template v-else-if="field.key=='granted_roles_names'">
                                        <!-- <b-button size="sm" variant="outline-primary" type="submit" @click="search()">Search</b-button> -->
                                        <!-- <v-select v-model="searchValues[field.key]" label="role_name" :options="roles"></v-select> -->
                                        <!-- while the column is named inherits_role_name actually object_meta_uuid is provided so the field name should be inherits_role_uuid -->
                                        <!-- :reduce makes return only the uuid not the whole object role_name:meta_object_uuid -->
                                        <v-select v-model="searchValues['inherits_roles_uuids']" label="role_name" :reduce="role_name => role_name.meta_object_uuid" :options="roles"></v-select>
                                    </template>
                                    <template v-else>
                                        <b-form-input v-model="searchValues[field.key]" type="search" :placeholder="field.label"></b-form-input>
                                    </template>
                                </td>
                            </template>

                            <!-- <template v-slot:cell(meta_object_uuid)="row"> -->
                            <template v-slot:cell(action)="row">
                                <b-button size="sm" variant="outline-danger" v-on:click.stop="" @click="show_update_modal('delete', row.item)">Delete</b-button>
                            </template>

                        </b-table>
                    </b-form>
                </template>

                <b-pagination v-if="totalItems > limit" size="md" :total-rows="totalItems" v-model="currentPage" :per-page="limit"  align="center"></b-pagination>

                <!-- ======================= MODAL UPDATE & DELETE ================== -->
                <b-modal
                        id="crud-modal"
                        :title="modalTitle"
                        :header-bg-variant="modalVariant"
                        header-text-variant="light"
                        body-bg-variant="light"
                        body-text-variant="dark"
                        :ok-title="ButtonTitle"
                        :ok-variant="ButtonVariant"
                        centered
                        @ok="update_modal_ok_handler"
                        :cancel-disabled="actionState"
                        :ok-disabled="loadingState"
                        :ok-only="actionState && !loadingState"
                        size="lg"
                >
                    <template v-if="!actionState">
                        <!-- <p>{{actionTitle}}</p> -->
                        <!-- apply filter "humanize" on the label -->
                        <b-form-group class="form-group" v-for="(value, index) in putValues" v-if="index!='meta_object_uuid'" v-bind:key="index" :label="index + ':' | humanize" label-align="right" label-cols="3">

                            <template v-if="index === 'granted_roles_uuids'">
                                <!-- show checkboxes with roles -->
                                <!--
                                <template v-for="(Role, index) in roles">
                                </template> -->
                                <!-- <b-form-group v-for="(Role, index) in roles" :label="Role.role_name" label-align="right">
                                </b-form-group> -->
                                <b-form-checkbox-group id="granted_roles" v-model="granted_roles" name="granted_roles">
                                    <!-- <b-form-checkbox v-for="(Role, index) in roles" :value="Role.meta_object_uuid">{{Role.role_name}}</b-form-checkbox> -->
                                    <!-- because the inherits_role_uuid is not included in the record_properties, only role name, the checkboxes will be driven by name (which is also unique) -->
                                    <!-- <b-form-checkbox v-for="(Role, index) in roles" :value="Role.role_name" v-bind:key="Role.role_name">{{Role.role_name}}</b-form-checkbox> -->
                                    <b-form-checkbox v-for="(Role, index) in roles" :value="Role.meta_object_uuid" v-bind:key="Role.role_name">{{Role.role_name}}</b-form-checkbox>
                                    <!-- {{ putValues }} -->
                                    <!-- {{granted_roles}} -->
                                </b-form-checkbox-group>

                            </template>
                            <template v-else-if="index.indexOf('password') != -1">
                                <b-form-input v-model="putValues[index]" :disabled="!editable_record_properties.includes(index)" type="password"></b-form-input>
                            </template>
                            <template v-else-if="index === 'user_is_disabled'">
                                <b-form-checkbox name="user_is_disabled" :value="true" :unchecked-value="false" v-model="putValues.user_is_disabled"></b-form-checkbox>
                            </template>
                            <template v-else-if="action === 'delete'">
                                <b-form-input :value="value" disabled></b-form-input>
                            </template>
                            <template v-else>
                                <b-form-input v-model="putValues[index]" :disabled="!editable_record_properties.includes(index)"></b-form-input>
                            </template>
                        </b-form-group>

                        <!--
                        <b-form-group label="User Password:" label-align="right" label-cols="3">
                            <b-form-input v-model="putValues['user_password']"></b-form-input>
                        </b-form-group>
                        <b-form-group label="Password Confirmation:" label-align="right" label-cols="3">
                            <b-form-input v-model="putValues['user_password_confirmation']"></b-form-input>
                        </b-form-group>
                        -->
                    </template>

                    <template v-else>
                        <p v-if="loadingState">
                            {{loadingMessage}}
                            ...
                        </p>
                        <p v-else>
                            <template v-if="requestError == ''">
                                {{successfulMessage}}
                            </template>
                            <template v-else>
                                The operation can not be performed due to an error:<br />
                                {{requestError}}
                            </template>
                        </p>
                    </template>
                </b-modal>


                <!-- ======================= MODAL PERMISSIONS ================== -->
                <b-modal
                        id="crud-permissions"
                        :title="title_permissions"
                        header-bg-variant="success"
                        header-text-variant="light"
                        body-bg-variant="light"
                        body-text-variant="dark"
                        hide-footer
                        size="lg"
                >
                    <b-table
                            striped
                            show-empty
                            :items="items_permissions"
                            :fields="fields_permissions"
                            empty-text="No records found!"
                            head-variant="dark"
                            table-hover
                            :busy.sync="isBusy_permissions"
                    >

                        <!-- permision_uuid is just a value that can not be used here as it is only for the first row/role -->
                        <template v-slot:[setSlotCell(action_name)]="row" v-for="(permission_uuid, action_name) in items_permissions[0].permissions">
                            <b-form-checkbox :value="row.item.permissions[action_name] ? row.item.permissions[action_name] : 0" unchecked-value="" @change="toggle_permission(row.item, action_name, row.item.permissions[action_name] ? 1 : 0)" v-model="row.item.permissions[action_name]"></b-form-checkbox>
                        </template>

                    </b-table>
                </b-modal>

            </div>
        </div>
    </div>

</template>

<script>
    import Hook from '@GuzabaPlatform.Platform/components/hooks/Hooks.vue'
    import ToastMixin from '@GuzabaPlatform.Platform/ToastMixin.js'

    import vSelect from 'vue-select'
    import 'vue-select/dist/vue-select.css'

    export default {
        name: "RolesAdmin",
        mixins: [
            ToastMixin,
        ],
        components: {
            Hook,
            vSelect,
        },
        data() {
            return {
                checkbox_test: '0',
                limit: 10,
                currentPage: 1,
                totalItems: 0,

                //selectedClassName: '',
                //selectedClassNameShort: '',
                sortBy: 'none',
                sortDesc: false,

                searchValues: {},
                putValues: {},

                requestError: '',

                action: '',
                actionTitle: '',
                modalTitle: '',
                modalVariant: '',
                ButtonTitle: '',
                ButtonVariant: '',

                crudObjectUuid: '',

                actionState: false,
                loadingState: false,

                loadingMessage: '',
                successfulMessage: '',

                items: [],
                fields: [],//these are the columns
                record_properties: [],
                editable_record_properties: [],

                items_permissions: [
                    //must have a default even empty value to avoid the error on template load
                    {
                        permissions: [],
                    }
                ],
                fields_permissions: [],
                fields_permissions_base: [
                    {
                        key: 'role_id',
                        label: 'Role ID',
                        sortable: true
                    },
                    {
                        key: 'role_name',
                        label: 'Role Name',
                        sortable: true
                    },
                ],

                title_permissions: "Permissions",
                isBusy_permissions: false,
                selectedObject: {},

                newObject: {},

                /** The non-user roles */
                roles: [],
                /** Used by the modification modal */
                granted_roles: [],
            }
        },
        methods: {
            // https://stackoverflow.com/questions/58140842/vue-and-bootstrap-vue-dynamically-use-slots
            setSlotCell(action_name) {
                return `cell(${action_name})`;
            },

            submitSearch(evt){
                evt.preventDefault()
                this.search()
            },

            get_roles_for_dropdown() {
                this.$http.get('/admin/users/roles')
                    .then(resp => {
                        this.roles = resp.data.roles;
                    })
                    .catch(err => {
                        this.$bvToast.toast('Roles could not be loaded due to server error.' + '\n' + err.response.data.message)
                    });
            },

            get_roles() {

                this.fields = [];
                this.newObject = {};

                for (let key in this.searchValues) {
                    if (this.searchValues[key] == '') {
                        delete this.searchValues[key];
                    }
                }

                let objJsonStr = JSON.stringify(this.searchValues);//this is passed as GET so needs to be stringified
                let searchValuesToPass = encodeURIComponent(window.btoa(objJsonStr));

                let self = this;

                this.$http.get('/admin/roles/' + self.currentPage + '/' + self.limit + '/'+ searchValuesToPass + '/' + this.sortBy + '/' + this.sortDesc)
                    .then(resp => {
                        // self.fields.push({
                        //     label: 'UUID',
                        //     key: key,
                        //     sortable: true
                        // });
                        for (let i in resp.data.listing_columns) {
                            let key = resp.data.listing_columns[i];
                            self.fields.push({
                                key: key,
                                sortable: true
                            });
                            self.newObject[key] = '';
                        }
                        self.fields.push({
                            label: 'Action',
                            key: 'action',
                            sortable: true
                        });

                        self.items = resp.data.data;
                        for (let aa = 0; aa < this.items.length; aa++) {
                            this.items[aa]['granted_roles_names'] = this.items[aa]['granted_roles_names'].join(',');
                            //this.items[aa]['granted_roles_uuids'] = this.items[aa]['granted_roles_uuids'].join(',');
                        }
                        self.totalItems = resp.data.totalItems;

                        self.record_properties = resp.data.record_properties;
                        self.editable_record_properties = resp.data.editable_record_properties;
                    })
                    .catch(err => {
                        //console.log(err);
                        this.$bvToast.toast('Users data could not be loaded due to server error.' + '\n' + err.response.data.message)
                    });
            },

            search() {
                this.reset_params();
                this.get_users();
            },

            //reset_params(className){
            reset_params() {
                this.currentPage = 1;
                this.totalItems = 0;
                this.sortBy = 'user_name';
            },

            row_click_handler(record, index) {
                this.show_update_modal('put', record);
            },

            /**
             * Shows the modal dialog for updating/creating/deleting a user record
             * @param {string} action The actual HTTP method to be executed
             * @param {array} row
             */
            show_update_modal(action, row) {
                this.action = action;
                this.crudObjectUuid = null;
                this.putValues = {};

                for (let key in row) {
                    if (key == "meta_object_uuid") {
                        this.crudObjectUuid = row[key];
                        //} else if (!key.includes("meta_")){
                    } else if (!key.includes("meta_") && this.record_properties.includes(key)) { // show only the properties listed in record_properties
                        this.putValues[key] = row[key];
                    }
                }
                this.putValues['user_password'] = '';
                this.putValues['user_password_confirmation'] = '';

                this.granted_roles = this.putValues.granted_roles_uuids;
                //console.log(this.granted_roles);

                switch (this.action) {
                    case 'delete' :
                        this.modalTitle = 'Deleting object';
                        this.modalVariant = 'danger';
                        this.ButtonVariant = 'danger';
                        //this.actionTitle = 'Are you sure, you want to delete object:';
                        this.ButtonTitle = 'Delete';
                        break;

                    case 'put' :
                        this.modalTitle = 'Edit object';
                        this.modalVariant = 'success';
                        this.ButtonVariant = 'success';
                        this.ButtonTitle = 'Save';
                        break;

                    case 'post' :
                        this.modalTitle = 'Create new object';
                        this.modalVariant = 'success';
                        this.ButtonVariant = 'success';
                        this.ButtonTitle = 'Save';
                        break;
                }

                if (!this.crudObjectUuid && this.action != "post") {
                    this.requestError = "This object has no meta data!";
                    this.actionState = true;
                    this.loadingState = false;
                    this.ButtonTitle = 'Ok';
                } else {
                    this.actionState = false
                    this.loadingState = false
                }

                this.$bvModal.show('crud-modal');

            },

            update_modal_ok_handler(bvEvt) {
                if(!this.actionState) {
                    bvEvt.preventDefault() //if actionState is false, doesn't close the modal
                    this.actionState = true
                    this.loadingState = true

                    let self = this;
                    let sendValues = {};

                    //because of the custom login needed for handling the granted roles the ActiveRecordDefaultControllercan not be used
                    //let url = '/admin/crud-operations';
                    let url = '/admin/users/user';

                    console.log(this.putValues);

                    switch(this.action) {
                        case 'delete' :
                            self.loadingMessage = 'Deleting object with uuid: ' + this.crudObjectUuid;
                            url += '/' + this.crudObjectUuid;
                            break;

                        case 'put' :
                            self.loadingMessage = 'Saving object with uuid: ' + this.crudObjectUuid;
                            url += '/' + this.crudObjectUuid;
                            sendValues = this.putValues;
                            delete sendValues['meta_object_uuid'];
                            break;

                        case 'post' :
                            self.loadingMessage = 'Saving new object';
                            sendValues = this.putValues;
                            delete sendValues['meta_object_uuid'];
                            break;
                    }
                    //sendValues.crud_class_name = this.selectedClassName.split('\\').join('-');
                    sendValues.crud_class_name = 'GuzabaPlatform\\Platform\\Authorization\\Models\\User';
                    //due to the Roles management the basic CRUD operation can not be used and a custom controller is needed

                    this.$http({
                        method: this.action,
                        url: url,
                        data: sendValues
                    })
                        .then(resp => {
                            self.requestError = '';
                            self.successfulMessage = resp.data.message;
                            self.get_users(self.selectedClassName)
                        })
                        .catch(err => {
                            if (err.response.data.message) {
                                self.requestError = err.response.data.message;
                            } else {
                                self.requestError = err;
                            }
                        })
                        .finally(function(){
                            self.loadingState = false
                            self.actionState = true
                            self.ButtonTitle = 'OK';
                            self.ButtonVariant = 'success';
                        });
                }
            },

            sortingChanged(ctx) {
                this.sortBy = ctx.sortBy;
                this.sortDesc = ctx.sortDesc ? 1 : 0;

                this.get_users(this.selectedClassName);
            }
        },
        props: {
            contentArgs: {}
        },
        watch:{
            contentArgs: {
                handler: function(value) {
                    //this.get_users(value.selectedClassName.split('\\').join('.'));
                }
            },
            currentPage: {
                handler: function(value) {
                    //this.get_users(this.selectedClassName);
                }
            },
            // $route (to, from) { // needed because by default no class is loaded and when it is loaded the component for the two routes is the same.
            //     this.selectedClassName = this.$route.params.class.split('-').join('\\');
            //     //console.log("ASD " + this.selectedClassName)
            //     this.get_users(this.selectedClassName);
            //
            // }
        },
        mounted() {
            this.get_roles();
            this.get_roles_for_dropdown();

        },
    };

</script>

<style>
    .content {
        height: 100vh;
        top: 64px;
    }

    .tab {
        float: left;
        height: 100%;
        overflow: none;
        padding: 20px;
    }

    #sidebar{
        font-size: 10pt;
        border-width: 0 5px 0 0;
        border-style: solid;
        width: 30%;
        text-align: left;
    }

    #data {
        width: 65%;
        font-size: 10pt;
    }

    li {
        cursor: pointer;
    }

    .btn {
        width: 100%;
    }

    tr:hover{
        background-color: #ddd !important;
    }

    th:hover{
        background-color: #000 !important;
    }

    tr {
        cursor: pointer;
    }
</style>
