<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Equipment;
use App\Models\Level;
use App\Models\WorkoutType;
use App\Models\Workout;
use App\Models\Diet;
use App\Models\CategoryDiet;
use Illuminate\Support\Facades\App;
use App\Models\BodyPart;
use App\Models\Exercise;
use App\Models\Tags;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Models\Product;
use App\Models\Package;
use App\Models\ProductCategory;
use App\Helpers\AuthHelper;
use App\Models\AppSetting;

class HomeController extends Controller
{
    /*
     * Dashboard Pages Routs
     */
    public function index(Request $request)
    {
        $assets = ['chart', 'animation'];
        $auth_user = AuthHelper::authSession();
        $data['dashboard'] = [
            'total_equipment'   => Equipment::count(),
            'total_level'       => Level::count(),
            'total_bodypart'    => BodyPart::count(),
            'total_workouttype' => WorkoutType::count(),
            'total_exercise'    => Exercise::count(),
            'total_workout'     => Workout::count(),
            'total_diet'        => Diet::count(),
            'total_post'        => Post::count(),
        ];

        $data['exercise'] = Exercise::orderBy('id', 'desc')->take(10)->get();
        $data['workout'] = Workout::orderBy('id', 'desc')->take(10)->get();
        $data['diet'] = Diet::orderBy('id', 'desc')->take(10)->get();
        $data['post'] = Post::orderBy('id', 'desc')->take(10)->get();
        return view('dashboards.dashboard', compact('assets', 'data', 'auth_user'));
    }

    public function changeStatus(Request $request)
    {
        if(env('APP_DEMO')){
            $message = __('message.demo_permission_denied');
            $response = [
                'status'    => false,
                'message'   => $message
            ];
            return json_custom_response($response);
        }

        $type = $request->type;
        $message_form = "";
        $message = __('message.update_form',['form' => __('message.status')]);
        switch ($type) {
            case 'role':
                    $role = Role::find($request->id);
                    $role->status = $request->status;
                    $role->save();
                    break;
            default:
                    $message = 'error';
                break;
        }

        if($message_form != null){
            $message =  __('message.added_form',['form' => $message_form ]);
            if($request->status == 0){
                $message = __('message.remove_form',['form' => $message_form ]);
            }
        }
        
        return json_custom_response(['message'=> $message , 'status' => true]);
    }

    public function removeFile(Request $request)
    {
        $type = $request->type;
        $data = null;

        switch ($type) {
            case 'equipment_image':
                $data = Equipment::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.equipment') ]);
                break;
            case 'workout_image':
                $data = Workout::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.workout') ]);
                break;
            case 'categorydiet_image':
                $data = CategoryDiet::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.categorydiet') ]);
                break;
            case 'diet_image':
                $data = Diet::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.diet') ]);
                break;
             case 'category_image':
                $data = Category::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.category') ]);
                break;
             case 'level_image':
                $data = Level::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.level') ]);
                break;
            case 'bodypart_image':
                $data = BodyPart::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.bodypart') ]);
                break;
            case 'exercise_image':
                $data = Exercise::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.exercise') ]);
                break;
            case 'exercise_video':
                $data = Exercise::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.exercise') ]);
                break;
            case 'post_image':
                $data = Post::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.post') ]);
                break;   
            case 'productcategory_image':
                $data = ProductCategory::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.productcategory') ]);
                break;
            case 'product_image':
                $data = Product::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.product') ]);
                break;
            default:
                $data = AppSetting::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.image') ]);
            break;
            
        }

        if($data != null){
            $data->clearMediaCollection($type);
        }

        $response = [
                'status' => true,
                'id' => $request->id,
                'image' => getSingleMedia($data,$type),
                'preview' => $type."_preview",
                'message' => $message
        ];
        return json_custom_response($response);
    }

    public function changeLanguage($locale)
    {
        App::setLocale($locale);
        session()->put('locale', $locale);
        return redirect()->back();
    }
    
    public function getAjaxList(Request $request)
    {
        $items = array();
        $value = $request->q;
        
        switch ($request->type) {
            case 'permission':
                $items = Permission::select('id','name as text')->whereNull('parent_id');
                if($value != ''){
                    $items->where('name', 'LIKE', $value.'%');
                }
                $items = $items->get();
                break;
        case 'categorydiet':
            $items = CategoryDiet::select('id','title as text')->where('status','active');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'level':
            $items = Level::select('id','title as text')->where('status','active');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'equipment':
            $items = Equipment::select('id','title as text')->where('status','active');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'workout_type':
            $items = WorkoutType::select('id','title as text')->where('status','active');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'bodypart':
            $items = BodyPart::select('id','title as text')->where('status','active');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'exercise':
            $items = Exercise::select('id','title as text')->where('status','active');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'tags':
                $items = Tags::select('id','title as text');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'category':
                $items = Category::select('id','title as text')->where('status','active');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
                case 'diet':
                    $items = Diet::select('id','title as text')->where('status','active');
                    if($value != ''){
                        $items->where('title', 'LIKE', '%'.$value.'%');
                    }
                    $items = $items->get();
                    break;  
            case 'user':
                    $items = User::select('id','id as text')->where('status','active');
                    if($value != ''){
                        $items->where('id', 'LIKE', '%'.$value.'%');
                    }
                    $items = $items->get();
                    break;   
        case 'productcategory':
                $items = ProductCategory::select('id','title as text');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'workout':
                $items = Workout::select('id','title as text');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'hours':
                $data = [];
                for ($x = 0; $x < 24; $x++) {
                    
                    $val = $x < 10 ? '0'.$x : $x ;
                    $data[] = [
                        'id' => $val,
                        'text' => $val,
                    ];
                }
               $items = $data;
                break;

        case 'minute':
                    $data = [];
                    for ($x = 0; $x < 60; $x++) {
                        $val = $x < 10 ? '0'.$x : $x ;
                        $data[] = [
                            'id' => $val,
                            'text' => $val,
                        ];
                    }
                   $items = $data;
                    break;

        case 'second':
                        $data = [];
                        for ($x = 0; $x < 60; $x++) {
                            $val = $x < 10 ? '0'.$x : $x ;
                            $data[] = [
                                'id' => $val,
                                'text' => $val,
                            ];
                        }
                       $items = $data;
                        break;
        case 'package':
            $items = Package::select('id','name as text')->where('status','active');
                if($value != ''){
                    $items->where('name', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;            
            default :
                break;
        }
        
        return response()->json(['status' => true, 'results' => $items]);
    }

    /*
     * Auth Routs
     */
    public function signin(Request $request)
    {
        return view('auth.login');
    }
    public function signup(Request $request)
    {
        return view('auth.register');
    }
    public function confirmmail(Request $request)
    {
        return view('auth.confirm-mail');
    }
    public function lockscreen(Request $request)
    {
        return view('auth.lockscreen');
    }
    public function recoverpw(Request $request)
    {
        return view('auth.forgot-password');
    }
    public function userprivacysetting(Request $request)
    {
        return view('auth.user-privacy-setting');
    }

    /*
     * Error Page Routs
     */

    public function error404(Request $request)
    {
        return view('errors.error404');
    }

    public function error500(Request $request)
    {
        return view('errors.error500');
    }
    public function maintenance(Request $request)
    {
        return view('errors.maintenance');
    }

    /*
     * Extra Page Routs
     */
    public function privacyPolicy()
    {
        $data = SettingData('privacy_policy', 'privacy_policy');
        return view('pages.privacy-policy', compact('data'));
    }

    public function termsCondition()
    {
        $data = SettingData('terms_condition', 'terms_condition');
        
        return view('pages.terms-condition', compact('data'));
    }
}
