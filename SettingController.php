<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AppSetting;
use App\Models\Setting;
use App\Models\User;
use App\Models\PaymentGateway;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use App\Helpers\EnvChange;
use App\Helpers\LanguageHelper;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

    }

    public function settings(Request $request)
    {
        $auth_user = auth()->user();
        
        $pageTitle = __('message.setting');
        $page = $request->page;

        if($page == ''){
            if($auth_user->hasAnyRole(['admin'])){
                $page = 'general-setting';
            }else{
                $page = 'profile-form';
            }
        }

        return view('setting.index',compact('page', 'pageTitle' ,'auth_user'));
    }

    public function layoutPage(Request $request)
    {
        $page = $request->page;
        if( $page == 'payment-setting' ) {
            $type = isset($request->type) ? $request->type : 'stripe';
        }
        $auth_user = auth()->user();
        $user_id = $auth_user->id;
        $settings = AppSetting::first();
        $user_data = User::find($user_id);
        $envSettting = $envSettting_value = [];
               
        if(count($envSettting) > 0 ){
            $envSettting_value = Setting::whereIn('key',array_keys($envSettting))->get();
        }
        if($settings == null){
            $settings = new AppSetting;
        }elseif($user_data == null){
            $user_data = new User;
        }
        switch ($page) {
            case 'password-form':
                $data  = view('setting.'.$page, compact('settings','user_data','page'))->render();
                break;
            case 'profile-form':
                $assets = ['phone'];
                $data  = view('setting.'.$page, compact('settings','user_data','page', 'assets'))->render();
                break;
            case 'mail-setting':
                $data  = view('setting.'.$page, compact('settings','page'))->render();
                break;
            case 'mobile-config':
                $setting = Config::get('mobile-config');
                $getSetting = [];
                foreach($setting as $k=>$s){
                    foreach ($s as $sk => $ss){
                        $getSetting[]=$k.'_'.$sk;
                    }
                }
                
                $setting_value = Setting::whereIn('key',$getSetting)->get();

                $data  = view('setting.'.$page, compact('setting', 'setting_value', 'page'))->render();
                break;
                case 'payment-setting':
                    $payment_setting_data = PaymentGateway::where('type',$type)->first();
                    $data  = view('setting.'.$page, compact('settings', 'page', 'type', 'payment_setting_data'))->render();
                break;
                case 'subscription-setting':
                    $settings = SettingData('subscription', 'subscription_system') ?? 1;
                    $data  = view('setting.'.$page, compact('settings','page'))->render();
                break;
            default:
                $data  = view('setting.'.$page, compact('settings','page','envSettting'))->render();
                break;
        }
        return response()->json($data);
    }

    public function settingUpdate(Request $request)
    {
        $data = $request->all();
        
        foreach($data['key'] as $key => $val){
            $value = ( $data['value'][$key] != null) ? $data['value'][$key] : null;
            $input = [
                'type' => $data['type'][$key],
                'key' => $data['key'][$key],
                'value' => ( $data['value'][$key] != null) ? $data['value'][$key] : null,
            ];
            Setting::updateOrCreate(['key'=>$input['key']],$input);
            EnvChange::envChanges($data['key'][$key],$value);
        }
        return redirect()->route('setting.index', ['page' => 'mobile-config'])->withSuccess( __('message.updated'));
    }
    
    public function settingsUpdates(Request $request)
    {
        $page = $request->page;
        
        $language_option = $request->language_option;

        if(!is_array($language_option)){
            $language_option=(array)$language_option;
        }

        array_push($language_option, $request->env['DEFAULT_LANGUAGE']);

        $request->merge(['language_option' => $language_option]);

        $request->merge(['site_name' => str_replace("'", "", str_replace('"', '', $request->site_name))]);

        $res = AppSetting::updateOrCreate([ 'id' => $request->id ], $request->all());

        $type = 'APP_NAME';
        $env = $request->env;

        $env['APP_NAME'] = $res->site_name;
        foreach ($env as $key => $value){
            EnvChange::envChanges($key, $value);
        }

        $message = '';
        
        App::setLocale($env['DEFAULT_LANGUAGE']);
        session()->put('locale', $env['DEFAULT_LANGUAGE']);
        
        storeMediaFile($res,$request->site_logo, 'site_logo');
        storeMediaFile($res,$request->site_dark_logo, 'site_dark_logo');
        storeMediaFile($res,$request->site_mini_logo, 'site_mini_logo');
        storeMediaFile($res,$request->site_dark_mini_logo, 'site_dark_mini_logo');
        storeMediaFile($res,$request->site_favicon, 'site_favicon');
        
        appSettingData('set');

        LanguageHelper::createLangFile($env['DEFAULT_LANGUAGE']);

        return redirect()->route('setting.index', ['page' => $page])->withSuccess( __('message.updated'));
    }
    
    public function envChanges(Request $request)
    {
        $page = $request->page;
        
        if(!auth()->user()->hasRole('admin')) {
            abort(403, __('message.action_is_unauthorized'));
        }
        $env = $request->ENV;
        $envtype = $request->type;

        foreach ($env as $key => $value){
            EnvChange::envChanges($key, str_replace('#','',$value));
        }
        Artisan::call('cache:clear');
        return redirect()->route('setting.index', ['page' => $page])->withSuccess(ucfirst($envtype).' '.__('message.updated'));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $data = $request->all();

        $user_id = $user->id;
        $validator = Validator::make($data,[
            'username' => 'required|unique:users,username,'.$user_id,
            'email' => 'required|max:191|email|unique:users,email,'.$user_id,
            'phone_number' => 'nullable|max:20|unique:users,phone_number,'.$user_id,
        ]);
        
        if($validator->fails()) {
            return redirect()->route('setting.index', ['page' => 'profile-form'])->with('errors', $validator->errors());
        }
        $user->fill($data)->update();
        storeMediaFile($user, $request->profile_image, 'profile_image');

        return redirect()->route('setting.index', ['page' => 'profile-form'])->withSuccess( __('message.profile').' '.__('message.updated'));
    }

    public function changePassword(Request $request)
    {
        $user = User::where('id',Auth::user()->id)->first();

        if($user == "") {
            $message = __('message.not_found_entry',[ 'name' => __('message.user') ]);
            return json_message_response($message,400);   
        }
        
        $validator= Validator::make($request->all(), [
            'old' => 'required|min:8|max:255',
            'password' => 'required|min:8|confirmed|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->route('setting.index', ['page' => 'password-form'])->with('errors',$validator->errors());
        }
           
        $hashedPassword = $user->password;

        $match = Hash::check($request->old, $hashedPassword);

        $same_exits = Hash::check($request->password, $hashedPassword);
        if ($match)
        {
            if($same_exits){
                $message = __('message.old_new_pass_same');
                return redirect()->route('setting.index', ['page' => 'password-form'])->with('error',$message);
            }

			$user->fill([
                'password' => Hash::make($request->password)
            ])->save();
            Auth::logout();
            $message = __('message.password_change');
            return redirect()->route('setting.index', ['page' => 'password-form'])->withSuccess($message);
        }
        else
        {
            $message = __('message.valid_password');
            return redirect()->route('setting.index', ['page' => 'password-form'])->with('error',$message);
        }
    }
    
    public function termAndCondition(Request $request)
    {
        $setting_data = Setting::where('type','terms_condition')->where('key','terms_condition')->first();
        $pageTitle = __('message.terms_condition');
        $assets = ['textarea'];
        return view('setting.term-condition-form',compact('setting_data', 'pageTitle', 'assets'));
    }

    public function saveTermAndCondition(Request $request)
    {
        if(!auth()->user()->hasRole('admin')) {
            abort(403, __('message.action_is_unauthorized'));
        }
        $setting_data = [
                        'type'  => 'terms_condition',
                        'key'   =>  'terms_condition',
                        'value' =>  $request->value 
                    ];
        $result = Setting::updateOrCreate(['id' => $request->id],$setting_data);
        if($result->wasRecentlyCreated)
        {
            $message = __('message.save_form',['form' => __('message.terms_condition')]);
        }else{
            $message = __('message.update_form',['form' => __('message.terms_condition')]);
        }

        return redirect()->route('pages.term_condition')->withsuccess($message);
    }

    public function privacyPolicy(Request $request)
    {
        $setting_data = Setting::where('type','privacy_policy')->where('key','privacy_policy')->first();
        $pageTitle = __('message.privacy_policy');
        $assets = ['textarea'];

        return view('setting.privacy-policy-form',compact('setting_data', 'pageTitle', 'assets'));
    }

    public function savePrivacyPolicy(Request $request)
    {
        if(!auth()->user()->hasRole('admin')) {
            abort(403, __('message.action_is_unauthorized'));
        }
        $setting_data = [
                        'type'   => 'privacy_policy',
                        'key'   =>  'privacy_policy',
                        'value' =>  $request->value 
                    ];
        $result = Setting::updateOrCreate(['id' => $request->id],$setting_data);
        if($result->wasRecentlyCreated)
        {
            $message = __('message.save_form',['form' => __('message.privacy_policy')]);
        }else{
            $message = __('message.update_form',['form' => __('message.privacy_policy')]);
        }

        return redirect()->route('pages.privacy_policy')->withsuccess($message);
    }
    public function paymentSettingsUpdate(Request $request)
    { 
        if(!auth()->user()->hasRole('admin')) {
            abort(403, __('message.action_is_unauthorized'));
        }
        $data = $request->all();
        $result = PaymentGateway::updateOrCreate([ 'type' => request('type') ],$data);
        storeMediaFile($result,$request->gateway_image, 'gateway_image');
        return redirect()->route('setting.index', ['page' => 'payment-setting'])->withSuccess( __('message.updated'));
    }
    
    public function subscriptionSettingsUpdate(Request $request)
    {
        if(!auth()->user()->hasRole('admin')) {
            abort(403, __('message.action_is_unauthorized'));
        }
        $subscription_data = [
                        'type'   => 'subscription',
                        'key'   =>  'subscription_system',
                        'value' =>  $request->subscription_system 
                    ];
        
        Setting::updateOrCreate(['type' => $subscription_data['type'], 'key' => $subscription_data['key'] ], $subscription_data);

        return redirect()->route('setting.index', ['page' => 'subscription-setting'])->withSuccess( __('message.updated'));
    }

}
 