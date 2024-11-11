<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
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

    public function addComment(Request $request)
    {
        $request->validate([
            'content' => 'required|string|max:500',
            'feed_id' => 'required|exists:feeds,id',
        ]);

        $feedId = $request->input('feed_id');
        $comment = Comment::create([
            'feed_id' => $feedId,
            'user_id' => Auth::id(),
            'content' => $request->content,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Comment added successfully.',
            'data' => $comment,
        ], 201);
    }

    // public function getComments(Request $request)
    // {
    //     $request->validate([
    //         'feed_id' => 'required|exists:feeds,id',
    //     ]);
    //     $feedId= $request->input('feed_id');
    //     $comments = Comment::with('user')->where('feed_id', $feedId)->get();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Comments retrieved successfully.',
    //         'data' => $comments,
    //     ], 200);
    // }


    public function getComments(Request $request)
    {
        $request->validate([
            'feed_id' => 'required|exists:feeds,id',
        ]);

        $feedId = $request->input('feed_id');

        // Retrieve comments along with the user data
        $comments = Comment::with('user')->where('feed_id', $feedId)->get();

        // Attach profile image to each comment's user
        foreach ($comments as $comment) {
            $comment->user->profile_image = getSingleMedia($comment->user, 'profile_image', null);
        }

        foreach ($comments as $comment) {
            // Get the profile image of the user who made the comment
            $comment->user->profile_image = getSingleMedia($comment->user, 'profile_image', null);
        }

        return response()->json([
            'status' => true,
            'message' => 'Comments retrieved successfully.',
            'data' => $comments,
        ], 200);
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
