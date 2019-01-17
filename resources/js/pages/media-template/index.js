import Vue from 'vue';
import './../../common';
import Form from './../../common/form';
import 'vue-toastr-2/dist/vue-toastr-2.min.css'
import axios from 'axios';
import {generateRoute} from './../../common/helpers'

// Toastr Library
import VueToastr2 from 'vue-toastr-2'
window.toastr = require('toastr');
Vue.use(VueToastr2);
// Chart Library
import {filter} from 'lodash';
import Modal from 'bootstrap-vue'
Vue.use(Modal);

window['app'] = new Vue({
    el: '#template-index',
    components: {
        'pm-pagination': require('./../../components/pm-pagination/pm-pagination'),
        'spinner-icon': require('./../../components/spinner-icon/spinner-icon'),
        'media-type': require('./../../components/media-type/media-type'),
    },
    computed: {
        pagination: function () {
            return {
                page: this.searchForm.page,
                per_page: this.searchForm.per_page,
                total: this.total
            };
        },
        template_text: function () {
            if (this.media_template.type == 'sms') {
                return this.media_template.text_message;
            }
            if (this.media_template.type == 'email') {
                return this.media_template.email_text;
            }

            return;
        }
    },
    data: {
        searchFormUrl: null,
        searchForm: new Form({
            type: null,
            q: null,
            page: 1,
            per_page: 15,
        }),
        isLoading: true,
        total: null,
        templates: [],
        companies: [],
        searchTerm: '',
        companySelected: null,
        tableOptions: {
            mobile: 'lg'
        },
        mediaTemplateClosed: true,
        templateEdit: '',
        templateDelete: ''
    },
    mounted() {
        this.templateEdit = window.templateEdit;
        this.templateDelete = window.templateDelete;
        this.searchFormUrl = window.searchFormUrl;
        this.companySelected = window.companySelected;
        this.searchForm.q = window.q;

        axios
            .get(window.searchFormUrl, {
                headers: {
                    'Content-Type': 'application/json'
                },
                params: {
                    per_page: 100
                },
                data: null
            })
            .then(response => {
                this.templates = response.data.data;
            });

        this.fetchData();
    },
    methods: {
        onCompanySelected: function () {
            this.searchForm.page = 1;
            return this.fetchData();
        },
        fetchData: function () {
            if (this.companySelected) {
                this.searchForm.companySelected = this.companySelected.id;
            } else {
                this.searchForm.companySelected = null;
            }
            this.isLoading = true;
            this.searchForm.get(this.searchFormUrl)
                .then(response => {
                    this.templates = response.data;
                    this.searchForm.page = response.current_page;
                    this.searchForm.per_page = response.per_page;
                    this.total= response.total;
                    this.isLoading = false;
                })
                .catch(error => {
                    this.$toastr.error("Unable to get templates");
                });
        },
        deleteTemplate: function (id, idx) {
            var route = generateRoute(this.templateDelete, {templateId: id});
            this.$swal({
                title: "Are you sure?",
                text: "You will not be able to undo this operation!",
                type: "warning",
                showCancelButton: true,
                confirmButtonColor: "#DD6B55",
                confirmButtonText: "Yes",
                cancelButtonText: "No",
                allowOutsideClick: false,
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return axios.delete(route);
                }
            }).then(result => {
                if (result.value) {
                    this.$toastr.success("User deleted");
                    setTimeout(function () {
                        this.templates.splice(index, 1);
                    }, 800);
                }
            }, error => {
                this.$toastr.error("Unable to delete user");
            });
        },
        onPageChanged: function (event) {
            this.searchForm.page = event.page;
            return this.fetchData();
        },
        generateRoute
    }
});
