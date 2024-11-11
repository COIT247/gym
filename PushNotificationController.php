<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PushNotification;
use App\DataTables\PushNotificationDataTable;
use App\Models\User;
use App\Notifications\CommonNotification;
use App\Notifications\DatabaseNotification;
use App\Models\Notification;
use App\Helpers\AuthHelper;

class PushNotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(PushNotificationDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.pushnotification')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('pushnotification-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('pushnotification-add') ? '<a href="'.route('pushnotification.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.pushnotification')]).'</a>' : '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('pushnotification-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title',[ 'form' => __('message.pushnotification')]);
        $relation = [
            'user' => User::where('user_type', 'user')->where('status','active')->get()->pluck('display_name', 'id'),
        ];
        
        return view('push_notification.form', compact('pageTitle')+$relation);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if( !auth()->user()->can('pushnotification-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pushnotification = PushNotification::create($request->all());

        storeMediaFile($pushnotification, $request->notification_image, 'notification_image');
        
        $notification_data = [
            'id' => $pushnotification->id,
            'push_notification_id' => $pushnotification->id,
            'type' => 'push_notification',
            'subject' => $pushnotification->title,
            'message' => $pushnotification->message,
        ];
        if( getMediaFileExit($pushnotification, 'notification_image') ) {
            $notification_data['image'] = getSingleMedia($pushnotification, 'notification_image');
        } else {
            $notification_data['image'] = null;
        }

        User::whereIn('id', $request->user)->chunk(20, function ($userdata) use ($notification_data) {
            foreach ($userdata as $user) {
                $user->notify(new CommonNotification($notification_data['type'], $notification_data));
                $user->notify(new DatabaseNotification($notification_data));
            }
        });

        return redirect()->route('pushnotification.index')->withSuccess(__('message.save_form', ['form' => __('message.pushnotification')]));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.pushnotification')]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('pushnotification-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        if(env('APP_DEMO')){
            $message = __('message.demo_permission_denied');
            if(request()->ajax()) {
                return response()->json(['status' => true, 'message' => $message ]);
            }
            return redirect()->route('pushnotification.index')->withErrors($message);
        }
        $pushnotification = PushNotification::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.pushnotification')]);

        if($pushnotification != '') {
            // $search = "push_notification_id".'":'.$id;
            // Notification::where('data','like',"%{$search}%")->delete();
            Notification::whereJsonContains('data->push_notification_id',$id);
            $pushnotification->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.pushnotification')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status, $message);
    }
}
