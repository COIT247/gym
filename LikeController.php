<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Models\Like;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LikeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }


    public function toggleLike(Request  $request)
    {
        $request->validate([
            'feed_id' => 'required|exists:feeds,id',
        ]);
        $feedId = $request->input('feed_id');
        $userId = Auth::id();
        $feed = Feed::findOrFail($feedId);
        $like = Like::where('user_id', $userId)->where('feed_id', $feedId)->first();

        if ($like) {
            $like->delete();
            return response()->json([
                'status' => true,
                'message' => 'Like removed successfully.',
                'liked' => false,
            ], 200);
        } else {
            Like::create([
                'user_id' => $userId,
                'feed_id' => $feedId,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Feed liked successfully.',
                'liked' => true,
            ], 201);
        }
    }




    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Like  $like
     * @return \Illuminate\Http\Response
     */
    public function show(Like $like)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Like  $like
     * @return \Illuminate\Http\Response
     */
    public function edit(Like $like)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Like  $like
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Like $like)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Like  $like
     * @return \Illuminate\Http\Response
     */
    public function destroy(Like $like)
    {
        //
    }
}
