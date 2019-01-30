import Vue from 'vue';
import './../../../common';
import Form from "../../../common/form";
import axios from "axios";
import Modal from 'bootstrap-vue';
Vue.use(Modal);
import {generateRoute} from '../../../common/helpers';

window['app'] = new Vue({
    el: '#recipients-index',
    computed: {
        pagination: function () {
            return {
                page: this.searchRecipientsForm.page,
                per_page: this.searchRecipientsForm.per_page,
                total: this.total
            };
        }
    },
    components: {
        'pm-pagination': require('./../../../components/pm-pagination/pm-pagination'),
        'spinner-icon': require('./../../../components/spinner-icon/spinner-icon'),
        'upload-recipient': require('./upload-recipient')
    },
    data: {
        recipientList: [],
        searchRecipientsForm: new Form({
            q: null,
            page: 1,
            per_page: 15
        }),
        loading: false,
        loadingStats: false,
        total: 0,
        uploadRecipientsUrl: ''
    },
    methods: {
        deleteDrop(drop) {
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
                    return axios.delete(generateRoute(window.deleteDropUrl, {'dropId': drop.id}));
                }
            }).then(result => {
                if (result.value) {
                    this.$swal({
                        title: 'Drop Deleted',
                        type: 'success',
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.replace(window.dropIndexUrl);
                    });
                }
            }, error => {
                this.$toastr.error('Unable to process your request');
            });
        },
        fetchData() {
            this.loading = true;
            this.searchRecipientsForm
                .get(window.searchRecipientsUrl)
                .then(response => {
                    console.log('response', response);
                    this.recipientList = response.data;
                    this.searchRecipientsForm.page = response.current_page;
                    this.searchRecipientsForm.per_page = response.per_page;
                    this.total = response.total;
                    this.loading = false;
                })
                .catch(error => {
                    this.$toastr.error("Unable to get recipient lists");
                });
        },
        generateRoute,
        onPageChanged(event) {
            this.searchRecipientsForm.page = event.page;
            return this.fetchData();
        },
        removeList(list, index) {
            this.loadingStats = true;
            axios
                .get(generateRoute(window.recipientListDeleteStatsUrl, {listId: list.id}))
                .then(({data: statsResponse}) => {
                    const deleteUrl = generateRoute(window.deleteRecipientUrl, {listId: list.id});
                    this.loadingStats = false;
                    this.$swal({
                        title: "Are you sure?",
                        html: '<div>You will not be able to undo this operation!</div>' +
                            '<div class="card mt-3">' +
                            '<div class="card-body">' +
                            '<div class="info-row"><b>Total Recipients: </b><span class="mr-2">' + statsResponse.total + '</span></div>' +
                            '<div class="info-row"><b>In Drops: </b><span class="mr-2">' + statsResponse.inDrops + '</span></div>' +
                            '<div class="info-row"><b>Sent media from drop (can\'t be deleted): </b><span class="mr-2">' + statsResponse.dropped + '</span></div>' +
                            '<div class="info-row"><b>Total to be deleted: </b><span class="mr-2">' + statsResponse.deletable + '</span></div>' +
                            '</div>' +
                            '</div>',
                        type: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#DD6B55",
                        confirmButtonText: "Yes",
                        cancelButtonText: "No",
                        allowOutsideClick: false,
                        showLoaderOnConfirm: true,
                        preConfirm: () => {
                            return axios.delete(deleteUrl);
                        }
                    }).then(result => {
                        if (result.value && statsResponse.deletable >= statsResponse.total) {
                            this.recipientList.splice(index, 1);
                            this.$swal({
                                title: 'Recipients Deleted',
                                type: 'success'
                            });
                        }
                    }, error => {
                        this.$toastr.error('Unable to process your request');
                    });
                });
        }
    },
    mounted() {
        this.fetchData();
        this.showRecipientListUrl = window.showRecipientListUrl;
        this.uploadRecipientsUrl = window.uploadRecipientsUrl;
    }
});
