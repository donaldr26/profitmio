<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');

//Route::get('/company/{company}', 'HomeController@companytest')->middleware('company');
Route::resource('/companies', 'CompanyController')->middleware('can:create,App\Company');

Route::get('/companies/{company}/dashboard', 'CompanyController@dashboard')->middleware('can:view,company')->name('companies.dashboard');

Route::get('/companies/{company}/adduser', 'CompanyController@createuser')->middleware('can:manage,company')->name('companies.createuser');
Route::post('/companies/{company}/adduser', 'CompanyController@storeuser')->middleware('can:manage,company')->name('companies.storeuser');

Route::get('/companies/{company}/campaign/{campaign}', 'CompanyController@campaignaccess')->middleware('can:manage,campaign')->name('companies.campaignaccess');
Route::post('/companies/{company}/campaign/{campaign}', 'CompanyController@setcampaignaccess')->middleware('can:manage,campaign')->name('companies.setcampaignaccess');

Route::resource('/users', 'UserController')->middleware('can:create,App\User');
Route::resource('/campaigns', 'CampaignController')->middleware('can:create,App\Campaign');
