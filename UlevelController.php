<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\UlevelDataTable;
use App\Models\Ulevel;
use App\Helpers\AuthHelper;
use App\Http\Requests\PointPerExRequest;
use App\Http\Requests\UlevelRequest;
use App\Models\PointPerEx;

class UlevelController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(UlevelDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.ulevel')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('ulevel-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('ulevel-add') ? '<a href="'.route('ulevel.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.ulevel')]).'</a>' : '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('ulevel-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $pageTitle = __('message.add_form_title',[ 'form' => __('message.ulevel')]);

        return view('ulevel.form', compact('pageTitle'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(UlevelRequest $request)
    {
        if( !auth()->user()->can('ulevel-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $ulevel = Ulevel::create($request->all());

        storeMediaFile($ulevel,$request->level_image, 'ulevel_image'); 

        return redirect()->route('ulevel.index')->withSuccess(__('message.save_form', ['form' => __('message.ulevel')]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = Ulevel::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if( !auth()->user()->can('ulevel-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = Ulevel::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.ulevel') ]);

        return view('ulevel.form', compact('data','id','pageTitle'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UlevelRequest $request, $id)
    {
        if( !auth()->user()->can('ulevel-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $ulevel = Ulevel::findOrFail($id);

        // level data...
        $ulevel->fill($request->all())->update();

        // Save level image...
        if (isset($request->level_image) && $request->level_image != null) {
            $ulevel->clearMediaCollection('ulevel_image');
            $ulevel->addMediaFromRequest('ulevel_image')->toMediaCollection('ulevel_image');
        }

        if(auth()->check()){
            return redirect()->route('ulevel.index')->withSuccess(__('message.update_form',['form' => __('message.ulevel')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.ulevel') ] ));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('ulevel-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $ulevel = Ulevel::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.ulevel')]);

        if($ulevel != '') {
            $ulevel->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.ulevel')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }




    
    public function storeUlevel(UlevelRequest $request)
    {
        // Optional: Uncomment for authorization check
        // if (!auth()->user()->can('ulevel-add')) {
        //     return redirect()->back()->withErrors(__('message.permission_denied_for_account'));
        // }
        $ulevelData = $request->validated();
        if ($request->hasFile('image')) {
            $imagePath = 'storage/' . $request->file('image')->store('levels/images', 'public');
            $ulevelData['image'] = $imagePath; 
        }
        $ulevel = Ulevel::create($ulevelData);
        return response()->json($ulevel, 201);  
    }

    public function storePoint(PointPerExRequest $request)
    {

        
        // Optional: Uncomment for authorization check
        // if (!auth()->user()->can('ulevel-add')) {
        //     return redirect()->back()->withErrors(__('message.permission_denied_for_account'));
        // }
        $pointData = $request->validated();
        if ($request->point_per_ex) {
            $pointData['point'] = $request->point_per_ex; 
        }
        $ulevel = PointPerEx::create($pointData);
        return response()->json($ulevel, 201);
    }

    public function UpdatePoint(PointPerExRequest $request)
    {
        // Optional: Uncomment for authorization check
        // if (!auth()->user()->can('ulevel-add')) {
        //     return redirect()->back()->withErrors(__('message.permission_denied_for_account'));
        // }
    
        // Validate and extract the request data
        $pointData = $request->validated();
    
        // Check if 'point_per_ex' is present and set it in the data
        if ($request->filled('point_per_ex')) {
            $pointData['point'] = $request->point_per_ex; 
        }
    
        // Retrieve the single record (assuming it's the only one)
        $ulevel = PointPerEx::first();
    
        if (!$ulevel) {
            return response()->json(['message' => 'Record not found'], 404);
        }
    
        // Update the record
        $ulevel->update($pointData);
    
        // Return a successful response
        return response()->json($ulevel, 200); // Use 200 OK for a successful update
    }
    

    

}