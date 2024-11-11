<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Feed;
use App\Models\Friend;
use App\Models\Like;
use App\Models\PointPerEx;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Models\Report;
use App\Models\Ulevel;
use App\Models\UserExerciseStatus;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FeedController extends Controller
{
    // public function index(Request $request)
    // {
    //     $currentUserId = Auth::id();
    //     $perPage = $request->get('per_page', 10);
    //     $feeds = Feed::where('user_id', '!=', $currentUserId)
    //         ->with('user') // Eager load the user relationship
    //         ->paginate($perPage);

    //     // Add like count and whether the current user has liked the feed
    //     $feeds->getCollection()->transform(function ($feed) use ($currentUserId) {
    //         $feed->like_count = $feed->likes()->count(); // Count of likes
    //         $feed->liked_by_current_user = Like::where('user_id', $currentUserId)
    //             ->where('feed_id', $feed->id)
    //             ->exists();
    //         // $feed->user_data = $feed->user; 

    //         return $feed;
    //     });

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Feeds retrieved successfully.',
    //         'pagination' => [
    //             'current_page' => $feeds->currentPage(),
    //             'last_page' => $feeds->lastPage(),
    //             'per_page' => $feeds->perPage(),
    //             'total' => $feeds->total(),
    //         ],
    //         'data' => $feeds->items(),
    //     ], 200);
    // }


    public function index(Request $request)
    {
        $currentUserId = Auth::id();
        
        $perPage = $request->get('per_page', 10);
        
        // Fetch blocked friends
        $blockFriends = Friend::where(function ($query) use ($currentUserId) {
            $query->where('user_id', $currentUserId)
                  ->orWhere('friend_id', $currentUserId);
        })
        ->where('status', 3) // Assuming status 3 means blocked
        ->get();
        
        // Get reported feeds by the current user
        $reportedFeedIds = Report::where('user_id', $currentUserId)->pluck('feed_id')->toArray();
        
        // Retrieve blocked friend IDs
        $blockedFriendIds = $blockFriends->map(function ($friend) use ($currentUserId) {
            return $friend->user_id == $currentUserId ? $friend->friend_id : $friend->user_id;
        })->toArray();
        
        // Prepare query for feeds
        $feedsQuery = Feed::whereNotIn('id', $reportedFeedIds)
            ->whereNotIn('user_id', $blockedFriendIds) // Exclude blocked friends
            ->with('user') // Eager load the user relationship
            ->orderBy('created_at', 'desc'); // Order by creation date
        
        // Apply filters if provided
        if ($request->has('display_name')) {
            $displayNameFilter = $request->input('display_name');
            $feedsQuery->whereHas('user', function($query) use ($displayNameFilter) {
                $query->where('display_name', 'like', "%{$displayNameFilter}%");
            });
        }
        
        if ($request->has('title')) {
            $titleFilter = $request->input('title');
            $feedsQuery->where('title', 'like', "%{$titleFilter}%");
        }
        
        if ($request->has('content')) {
            $contentFilter = $request->input('content');
            \Log::info("Filtering feeds by content: {$contentFilter}"); // Log the filter for debugging
            $feedsQuery->where('content', 'like', "%{$contentFilter}%");
        }
        
        // Execute the query and paginate results
        $feeds = $feedsQuery->paginate($perPage);
        
        // Log the resulting query for debugging
        \Log::info("Feeds query: " . $feedsQuery->toSql(), $feedsQuery->getBindings());
        
        // Attach profile image to each feed's user
        foreach ($feeds as $feed) {
            $feed->user->profile_image = getSingleMedia($feed->user, 'profile_image', null);
        }
        
        // Add like count, comment count, and whether the current user has liked or commented on the feed
        $feeds->getCollection()->transform(function ($feed) use ($currentUserId) {
            $feed->like_count = $feed->likes()->count();
            $feed->liked_by_current_user = Like::where('user_id', $currentUserId)
                ->where('feed_id', $feed->id)
                ->exists();
            $feed->comment_count = $feed->comments()->count();
            $feed->commented_by_current_user = Comment::where('user_id', $currentUserId)
                ->where('feed_id', $feed->id)
                ->exists();
        
            return $feed;
        });
        
        return response()->json([
            'status' => true,
            'message' => 'Feeds retrieved successfully.',
            'pagination' => [
                'current_page' => $feeds->currentPage(),
                'last_page' => $feeds->lastPage(),
                'per_page' => $feeds->perPage(),
                'total' => $feeds->total(),
            ],
            'data' => $feeds->items(),
        ], 200);
    }
    
    
    



    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {}
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:200',
            'content' => 'nullable|string|max:200',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:20480',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:20480',
            'video' => 'nullable|file|mimes:mp4,mov,avi|max:51200',
            'type' => 'required|integer|in:0,1', // Ensure type is required, an integer, and must be 0 or 1
        ]);

        if (!$request->hasFile('photo') && !$request->hasFile('video') && !$request->filled('content')) {
            return response()->json([
                'status' => false,
                'message' => 'At least one of photo, video, or text content is required.',
            ], 400);
        }

        $feedData = [
            'user_id' => Auth::id(),
            'title' => $request->input('title'),
            'media_type' => $request->input('media_type'),
            'content' => $request->input('content'),
            'type' => $request->input('type'), // Capture the type field
        ];

        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('photos', 'public');
            $feedData['photo'] = $photoPath;
        }
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('thumbnails', 'public');
            $feedData['thumbnail'] = $thumbnailPath;
        }
        if ($request->hasFile('video')) {
            $videoPath = $request->file('video')->store('videos', 'public');
            $feedData['video'] = $videoPath;
        }

        $feed = Feed::create($feedData);

        return response()->json([
            'status' => true,
            'message' => 'Feed added successfully.',
            'data' => $feed,
        ], 201);
    }

    public function report(Request $request)
    {
        $request->validate([
            'feed_id' => 'required|exists:feeds,id',
        ]);
        $feedId = $request->input('feed_id');
        $userId = Auth::id();
        $reportData = [
            'user_id' => Auth::id(),
            'feed_id' => $feedId,
            'reason' => $request->input('reason'),
        ];
        $report = Report::create($reportData);
        return response()->json([
            'status' => true,
            'message' => 'Feed reported successfully.',
            'data' => $report,
        ], 201);
    }



    public function show(Feed $feed) {}
    public function edit(Feed $feed) {}
    public function update(Request $request, Feed $feed) {}

    public function destroy(Request $request)
    {

        // Find the feed by ID

        $request->validate([
            'feed_id' => 'required|exists:feeds,id',
        ]);
        $feedId = $request->input('feed_id');
        $feed = Feed::findOrFail($feedId);
        $currentUserId = Auth::id();


        // Check if the current user is the owner of the feed
        if ($feed->user_id !== $currentUserId) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to delete this feed.',
            ], 403);
        }
        $feed->delete();

        return response()->json([
            'status' => true,
            'message' => 'Feed deleted successfully.',
        ], 200);
    }


    public function getUserData(Request $request)
    {
        // Validate the user_id query parameter
        $validator = Validator::make($request->query(), [
            'user_id' => 'required|integer|exists:users,id',
        ]);
    
        $currentUserId = Auth::id();
    
        // Check for validation errors
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }
    
        // Retrieve the user ID
        $userId = $request->query('user_id');
    
        // Find the user by ID
        $user = User::find($userId);

        // Fetch the UserProfile for the given user
        $profile = UserProfile::where('user_id', $userId)->first();
        
        // Convert user model to array
        $user_data = $user->toArray();
        
        // Check if profile exists and merge profile fields into the user data
        if ($profile) {
            $user_data['age'] = $profile->age;
            $user_data['weight'] = $profile->weight;
            $user_data['weight_unit'] = $profile->weight_unit;
            $user_data['height'] = $profile->height;
            $user_data['height_unit'] = $profile->height_unit;
            $user_data['address'] = $profile->address;
        }
    // $user = $profile;
        // Get the user's feeds without loading likes and comments
        $feeds = Feed::where('user_id', $user->id)
            ->get(); // Do not eager load likes and comments
    
        // Add like count, comment count, and whether the current user has liked or commented on the feed
        $feeds->transform(function ($feed) use ($currentUserId) {
            // Count of likes
            $feed->like_count = $feed->likes()->count();
            // Check if the current user liked the feed
            $feed->liked_by_current_user = Like::where('user_id', $currentUserId)
                ->where('feed_id', $feed->id)
                ->exists();
    
            // Count of comments
            $feed->comment_count = $feed->comments()->count();
            // Check if the current user commented on the feed
            $feed->commented_by_current_user = Comment::where('user_id', $currentUserId)
                ->where('feed_id', $feed->id)
                ->exists();
    
            // Remove user data from each feed
            unset($feed->user, $feed->likes, $feed->comments); // Ensure likes and comments are not included
    
            return $feed;
        });
    
        // Get user profile
        $user->profile_image = getSingleMedia($user, 'profile_image', null);
    
        // Check friendship status
        $friendshipStatus = 0; // Default to 0 (not friends)
    
        // Query the friends table to find the status
        $friendship = DB::table('friends')
            ->where(function ($query) use ($currentUserId, $userId) {
                $query->where('user_id', $currentUserId)
                    ->where('friend_id', $userId);
            })
            ->orWhere(function ($query) use ($currentUserId, $userId) {
                $query->where('user_id', $userId)
                    ->where('friend_id', $currentUserId);
            })
            ->first();
    
        // Check if friendship record was found
        if ($friendship) {
            if ($friendship->status == 1) {
                $friendshipStatus = ($friendship->user_id == $currentUserId) ? 1 : 2;
            } elseif ($friendship->status == 2) {
                $friendshipStatus = 3;
            } elseif ($friendship->status == 3) {
                $friendshipStatus = 4;
            }
        }
    
        // Calculate exercise points
        $pointsPerExercise = PointPerEx::value('point');
        $completedExercises = UserExerciseStatus::where('user_id', $userId)
            ->where('status', 1)
            ->get();
    
        $completedCount = $completedExercises->count();
        $totalPoints = $completedCount * $pointsPerExercise;
    
        // Get all levels
        $levels = Ulevel::orderBy('point')->get();
    
        // Calculate completed levels
        $completedLevels = 0;
        foreach ($levels as $level) {
            if ($totalPoints >= $level->point) {
                $completedLevels++;
            } else {
                break;
            }
        }
    
        // Get the user's current level
        $ulevel = Ulevel::where('point', '<=', $totalPoints)
            ->orderBy('point', 'desc')
            ->first();
    
        // Prepare response for exercise points
        $exerciseResponse = [
            'completed_exercises' => $completedCount,
            'total_points' => $totalPoints,
            'completed_levels' => $completedLevels,
            'level' => $ulevel ? [
                'level_title' => $ulevel->title,
                'level_points' => $ulevel->point,
                'level_image' => asset('storage/' . $ulevel->image),
            ] : (object)[],
        ];
    
        $friendship_status = DB::table('friends')
            ->where('user_id', $request->user_id)
            ->where('friend_id', Auth::id())
            ->where('status', 1)
            ->first(); // Use first() to get a single object
    
        // Prepare final response
        return response()->json([
            'status' => true,
            'message' => 'User data retrieved successfully.',
            'data' => [
                'user' => $user_data,
                'feeds' => $feeds,
                'friendship_status' => $friendshipStatus,
                'request_data' => $friendship_status, // Now this will also be a single object
                'badges' => $ulevel ? $exerciseResponse : [
                    'completed_exercises' => $completedCount,
                    'total_points' => $totalPoints,
                    'completed_levels' => $completedLevels,
                    'level' => (object)[],
                ],
            ],
        ], 200);
    }
    

    // public function indexForFriends(Request $request)
    // {
    //     $currentUserId = Auth::id();
    //     $user = Auth::user();

    //     $perPage = $request->get('per_page', 10);



    //     $user->profile_image = getSingleMedia($user, 'profile_image', null);
    //     $feeds = Feed::
    //         where('user_id', '=', $currentUserId)
    //         ->where('type', 1)  

    //         ->orderBy('created_at', 'desc') // Exclude reported feeds
    //         ->with('user') // Eager load the user relationship
    //         ->paginate($perPage);



    //     // Attach the profile image to each feed
    //     foreach ($feeds as $feed) {
    //         $feed->user->profile_image = getSingleMedia($feed->user, 'profile_image', null);
    //     }

    //     // Add like count and whether the current user has liked the feed
    //     $feeds->getCollection()->transform(function ($feed) use ($currentUserId) {
    //         $feed->like_count = $feed->likes()->count(); // Count of likes
    //         $feed->liked_by_current_user = Like::where('user_id', $currentUserId)
    //             ->where('feed_id', $feed->id)
    //             ->exists();

    //         return $feed;
    //     });

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Feeds retrieved successfully.',
    //         'pagination' => [
    //             'current_page' => $feeds->currentPage(),
    //             'last_page' => $feeds->lastPage(),
    //             'per_page' => $feeds->perPage(),
    //             'total' => $feeds->total(),
    //         ],
    //         'data' => [
    //            'current_user' => $feeds->items(),]
    //     ], 200);
    // }
    // public function indexForFriends(Request $request)
    // {
    //     $currentUserId = Auth::id();
    //     $perPage = $request->get('per_page', 10);

    //     // Retrieve friends' IDs
    //     $friendsIDs = Friend::where(function ($query) use ($currentUserId) {
    //             $query->where('user_id', $currentUserId)
    //                   ->orWhere('friend_id', $currentUserId);
    //         })
    //         ->where('status', 2) // Assuming status 2 means active friends
    //         ->get();

    //     // Create a list of friend IDs based on the current user's relationship
    //     $friendIds = $friendsIDs->map(function ($friend) use ($currentUserId) {
    //         return $friend->user_id == $currentUserId ? $friend->friend_id : $friend->user_id;
    //     })->unique()->toArray();


    //     // Retrieve feeds from friends
    //     $feedFriend = Feed::whereIn('user_id', $friendIds) // Get feeds from friends
    //         ->where('type', 1) // Assuming type 1 means public feeds
    //         ->orderBy('created_at', 'desc') // Order by creation date
    //         ->with('user') // Eager load the user relationship
    //         ->paginate($perPage);


    //     // Attach profile images to each feed's user
    //     foreach ($feedFriend as $feed) {
    //         $feed->user->profile_image = getSingleMedia($feed->user, 'profile_image', null);
    //     }

    //     // Add like count and whether the current user has liked the feed
    //     $feedFriend->getCollection()->transform(function ($feed) use ($currentUserId) {
    //         $feed->like_count = $feed->likes()->count(); // Count of likes
    //         $feed->liked_by_current_user = Like::where('user_id', $currentUserId)
    //             ->where('feed_id', $feed->id)
    //             ->exists();

    //         return $feed;
    //     });

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Feeds retrieved successfully.',
    //         'pagination' => [
    //             'current_page' => $feeds->currentPage(),
    //             'last_page' => $feeds->lastPage(),
    //             'per_page' => $feeds->perPage(),
    //             'total' => $feeds->total(),
    //         ],
    //         'user_friends' => $feedFriend->items(),
    //     ], 200);
    // }

    public function indexForFriends(Request $request)
    {
        $currentUserId = Auth::id();
        $perPage = $request->get('per_page', 10);

        // Retrieve current user's feeds
        $currentUserFeeds = Feed::where('user_id', $currentUserId)
            ->where('type', 1) // Assuming type 1 means public feeds
            ->orderBy('created_at', 'desc')
            ->with('user')
            ->paginate($perPage);

        // Attach profile images and additional data for current user's feeds
        foreach ($currentUserFeeds as $feed) {
            $feed->user->profile_image = getSingleMedia($feed->user, 'profile_image', null);
            $feed->like_count = $feed->likes()->count(); // Count of likes
            $feed->liked_by_current_user = Like::where('user_id', $currentUserId)
                ->where('feed_id', $feed->id)
                ->exists();
        }

        // Retrieve friends' IDs
        $friendsIDs = Friend::where(function ($query) use ($currentUserId) {
            $query->where('user_id', $currentUserId)
                ->orWhere('friend_id', $currentUserId);
        })
            ->where('status', 2) // Assuming status 2 means active friends
            ->get();

        // Create a list of friend IDs based on the current user's relationship
        $friendIds = $friendsIDs->map(function ($friend) use ($currentUserId) {
            return $friend->user_id == $currentUserId ? $friend->friend_id : $friend->user_id;
        })->unique()->toArray();

        // Retrieve feeds from friends
        $feedFriend = Feed::whereIn('user_id', $friendIds)
            ->where('type', 1) // Assuming type 1 means public feeds
            ->orderBy('created_at', 'desc')
            ->with('user')
            ->paginate($perPage);

        // Attach profile images and additional data for friends' feeds
        foreach ($feedFriend as $feed) {
            $feed->user->profile_image = getSingleMedia($feed->user, 'profile_image', null);
            $feed->like_count = $feed->likes()->count(); // Count of likes
            $feed->liked_by_current_user = Like::where('user_id', $currentUserId)
                ->where('feed_id', $feed->id)
                ->exists();
        }

        // Combine current user's feeds and friends' feeds
        $combinedFeeds = array_merge($currentUserFeeds->items(), $feedFriend->items());
        $totalFeeds = count($combinedFeeds); // Total feeds

        $responseData = [];

        // Group feeds by user
        foreach ($combinedFeeds as $feed) {
            $userId = $feed->user_id;

            if (!isset($responseData[$userId])) {
                // Flatten user data into the response
                $responseData[$userId] = [
                    'id' => $feed->user->id,
                    'username' => $feed->user->username,
                    'first_name' => $feed->user->first_name,
                    'last_name' => $feed->user->last_name,
                    'email' => $feed->user->email,
                    'phone_number' => $feed->user->phone_number,
                    'email_verified_at' => $feed->user->email_verified_at,
                    'user_type' => $feed->user->user_type,
                    'status' => $feed->user->status,
                    'gender' => $feed->user->gender,
                    'display_name' => $feed->user->display_name,
                    'profile_image' => $feed->user->profile_image,
                    'challenges' => []
                ];
            }

            // Add the feed to the challenges
            unset($feed->user); // Remove user data from feed
            $responseData[$userId]['challenges'][] = $feed;
        }

        return response()->json([
            'status' => true,
            'message' => 'Feeds retrieved successfully.',
            'pagination' => [
                'current_page' => $currentUserFeeds->currentPage(),
                'last_page' => max($currentUserFeeds->lastPage(), $feedFriend->lastPage()),
                'per_page' => $perPage,
                'total' => $totalFeeds,
            ],
            'data' => array_values($responseData) // Return the grouped data without numeric keys
        ], 200);
    }
}
