import Vue from 'vue';
import Form from './../../common/form';
import forEach from 'lodash';

window['app'] = new Vue({
    el: '#login',
    components: {
        'spinner-icon':  require('./../../components/spinner-icon/spinner-icon'),
    },
    data: {
        errors: [],
        errorMessage: null,
        userForm: new Form({
            email: null,
            password: null,
        }),
        loading: false
    },
    methods: {
        login() {
            this.loading = true;
            this.userForm
                .post(window.authUrl)
                .then(response => {
                    window.location.replace(response.redirect_url);
                }, error => {
                    if (error && error.errors) {
                        let errs = [];
                        for (const key of Object.keys(error.errors)) {
                            error.errors[key].forEach(msg => {
                                errs.push(msg);
                            });
                        }
                        this.errors = errs;
                    } else {
                        this.errorMessage = error.message;
                    }
                    this.loading = false;
                });
        },
    }
});
