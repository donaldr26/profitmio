import Vue from 'vue';
import './../../common';
import Form from './../../common/form';
import axios from 'axios';
// Chart Library
import VueChartkick from 'vue-chartkick';
import Chart from 'chart.js';
import {filter} from 'lodash';
import './../../filters/user-role.filter';
import {generateRoute} from './../../common/helpers';
import vue2Dropzone from 'vue2-dropzone';

Vue.use(VueChartkick, {adapter: Chart});

window['app'] = new Vue({
    el: '#user-view',
    components: {
        'campaign': require('./../../components/campaign/campaign'),
        'resumable': require('./../../components/resumable/resumable'),
        'pm-pagination': require('./../../components/pm-pagination/pm-pagination'),
        'spinner-icon': require('./../../components/spinner-icon/spinner-icon'),
        'user-role': require('./../../components/user-role/user-role')
    },
    computed: {
        countCompanies: function () {
            return this.companiesForList.length;
        },
        countActiveCampaigns: function () {
            return filter(this.campaigns, {
                status: 'Active'
            }).length;
        },
        countInactiveCampaigns: function () {
            return filter(this.campaigns, item => {
                return item.status !== 'Active';
            }).length;
        },
        companiesPagination: function () {
            return {
                page: this.searchCompanyForm.page,
                per_page: this.searchCompanyForm.per_page,
                total: this.totalCompanies
            };
        },
        campaignsPagination: function () {
            return {
                page: this.searchCampaignForm.page,
                per_page: this.searchCampaignForm.per_page,
                total: this.total
            };
        }
    },
    data: {
        editImage: false,
        searchCampaignFormUrl: null,
        searchCompanyForm: new Form({
            q: localStorage.getItem('companyQ'),
            page: 1,
            per_page: 15,
            user: null
        }),
        searchCampaignForm: new Form({
            company: localStorage.getItem('campaignCompany') ? JSON.parse(localStorage.getItem('campaignCompany')) : undefined,
            q: localStorage.getItem('campaignQ'),
            page: 1,
            per_page: 15,
            user: null
        }),
        showUserFormControls: false,
        loadingCompanies: true,
        loadingCampaigns: true,
        loadingInvitation: false,
        originalUser: {},
        total: null,
        totalCompanies: null,
        campaigns: [],
        companies: [],
        companiesForList: [],
        roles: ['admin', 'user'],
        searchTerm: '',
        campaignCompanySelected: null,
        tableOptions: {
            mobile: 'lg'
        },
        targetUrl: window.updateUserPhotoUrl,
        timezones: [],
        formUrl: '',
        loggedUserRole: '',
        user: new Form({
            id: window.user.id,
            first_name: window.user.first_name,
            last_name: window.user.last_name,
            email: window.user.email,
            phone_number: window.user.phone_number
        })
    },
    mounted() {
        this.timezones = window.timezones;
        this.campaignCompanySelected = window.campaignCompanySelected;
        this.searchCampaignForm.q = window.campaignQ;
        this.loggedUserRole = window.userRole;
        this.originalUser = window.user;

        axios
            .get(window.getCompanyUrl, {
                headers: {
                    'Content-Type': 'application/json'
                },
                params: {
                    per_page: 100,
                    user: window.user.id
                },
                data: null
            })
            .then(response => {
                this.companies = response.data.data;
            });

        this.fetchCampaigns();
        this.fetchCompanies();
    },
    methods: {
        canEditCompanyData(company) {
            return window.userRole === 'site_admin' || window.userRole === 'admin';
        },
        onCampaignCompanySelected() {
            this.searchCampaignForm.page = 1;
            return this.fetchCampaigns();
        },
        fetchCampaigns() {
            if (this.campaignCompanySelected) {
                this.searchCampaignForm.company = this.campaignCompanySelected.id;
                localStorage.setItem('campaignCompany', JSON.stringify(this.campaignCompanySelected));
            } else {
                this.searchCampaignForm.company = null;
                localStorage.removeItem('campaignCompany');
            }
            localStorage.setItem('campaignQ', this.searchCampaignForm.q);
            this.searchCampaignForm.user = window.user.id;
            this.loadingCampaigns = true;
            this.searchCampaignForm
                .get(window.searchCampaignFormUrl)
                .then(response => {
                    this.campaigns = response.data;
                    this.searchCampaignForm.page = response.current_page;
                    this.searchCampaignForm.per_page = response.per_page;
                    this.total = response.total;
                    this.loadingCampaigns = false;
                })
                .catch(error => {
                    this.$toastr.error("Unable to get campaigns");
                });
        },
        fetchCompanies() {
            this.searchCompanyForm.user = window.user.id;
            this.loadingCompanies = true;
            if (this.searchCompanyForm.q) {
                localStorage.setItem('companyQ', this.searchCompanyForm.q);
            } else {
                localStorage.removeItem('companyQ');
            }
            this.searchCompanyForm
                .get(window.searchCompaniesFormUrl)
                .then(response => {
                    this.companiesForList = response.data;
                    this.searchCompanyForm.page = response.meta.current_page;
                    this.searchCompanyForm.per_page = response.meta.per_page;
                    this.totalCompanies = response.meta.total;
                    this.loadingCompanies = false;
                })
                .catch(error => {
                    this.$toastr.error("Unable to get campaigns");
                });
        },
        onCampaignPageChanged(event) {
            this.searchCampaignForm.page = event.page;
            return this.fetchCampaigns();
        },
        onCompanyPageChanged(event) {
            this.searchCompanyForm.page = event.page;
            return this.fetchCompanies();
        },
        onFileAdded() {
            this.$refs.resumable.startUpload();
        },
        onFileSuccess(event) {
            const response = JSON.parse(event.message);
            this.originalUser.image_url = response.location;
            this.editImage = false;
        },
        resendInvitation(company) {
            this.loadingInvitation = true;
            axios
                .get(window.resendInvitationUrl, {
                    params: {
                        user: this.user.id,
                        company: company.id
                    },
                })
                .then(response => {
                    this.loadingInvitation = false;
                    this.$toastr.success('Invitation Sent!');
                }, () => {
                    this.loadingInvitation = false;
                    this.$toastr.error('Unable to process your request.');
                })
        },
        updateCompanyData(company) {
            const data = {
                company: company.id
            };
            if (company.role) {
                data.role = company.role;
            }
            if (company.timezone) {
                data.timezone = company.timezone;
            }
            axios
                .post(generateRoute(window.updateCompanyDataUrl, {'userId': window.user.id}), data)
                .then(response => {
                }, () => {
                    this.$toastr.error('Unable to process your request');
                });
        },
        cancelUser: function () {
            this.showUserFormControls = false;
            this.user = new Form({...this.originalUser});
        },
        saveUser: function () {
            this.loading = true;
            this.user
                .post(generateRoute(window.updateUserUrl, {userId: window.user.id}))
                .then(() => {
                    this.showUserFormControls = false;
                    this.$toastr.success('User updated!');
                    this.loading = false;
                    this.originalUser = this.user.data();
                })
                .catch(e => {
                    this.$toastr.error("Unable to process your request");
                    this.loading = false;
                });
        }
    }
});
