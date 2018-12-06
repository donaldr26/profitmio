// window.Vue = require('vue');
//
// /**
//  * Next, we will create a fresh Vue application instance and attach it to
//  * the page. Then, you may begin adding components to this application
//  * or customize the JavaScript scaffolding to fit your unique needs.
//  */
//
// Vue.component('example-component', require('./components/ExampleComponent.vue'));
//
// const app = new Vue({
//     el: '#app'
// });

import './bootstrap';
import Vue from 'vue'
import { Dropdown } from 'bootstrap-vue/es/components';

Vue.use(Dropdown);

new Vue({
    el: '#main-header'
});

new Vue({
    el: '#campaign-list',
    data: {
        open: {}
    },
    methods: {
        toggle: function (idx) {
            Vue.set(this.open, idx, !this.open[idx]);
            // this.open = merge(this.open, {[idx]: !this.open[idx]});
            // console.log(this, idx);
            // this.open[idx] = !this.open[idx];
        }
    }
});