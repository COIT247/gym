<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Friend;
use App\Models\User;
use App\Models\BlockedSuggestion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


class FriendController extends Controller
{
    const STATUS_PENDING = 1;
    const STATUS_ACCEPTED = 2;
    const STATUS_BLOCKED = 3;

    // Friend Suggestions (Friends of Friends)


    // public function allAvailableUsers(Request $request)
    // {
    //     $currentUserId = Auth::id();
    //     $user = Auth::user();

    //     // Pagination setting
    //     $perPage = $request->get('per_page', 10);

    //     // Get IDs of users who are already friends or have a pending friend request
    //     $excludedUserIds = Friend::where('user_id', $currentUserId)
    //         ->orWhere('friend_id', $currentUserId)
    //         ->pluck('friend_id')
    //         ->merge(Friend::where('friend_id', $currentUserId)
    //             ->pluck('user_id'))
    //         ->unique();

    //     // Retrieve all available users excluding the current user and excluded users
    //     $users = User::whereNotIn('id', $excludedUserIds)
    //         ->where('id', '!=', $currentUserId)
    //         ->orderBy('created_at', 'desc')
    //         ->paginate($perPage);

    //     // Attach the profile image to each user
    //     foreach ($users as $user) {
    //         $user->profile_image = getSingleMedia($user, 'profile_image', null);
    //     }

    //     // Format the response
    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Users retrieved successfully.',
    //         'pagination' => [
    //             'current_page' => $users->currentPage(),
    //             'last_page' => $users->lastPage(),
    //             'per_page' => $users->perPage(),
    //             'total' => $users->total(),
    //         ],
    //         'data' => $users->items(),
    //     ], 200);
    // }

    public function allAvailableUsers(Request $request)
    {
        $currentUserId = Auth::id();

        // Pagination setting
        $perPage = $request->get('per_page', 10);
        $usernameFilter = $request->get('display_name'); // Get username filter

        // Get IDs of users who are already friends or have a pending friend request
        $excludedUserIds = Friend::where('user_id', $currentUserId)
            ->orWhere('friend_id', $currentUserId)
            ->pluck('friend_id')
            ->merge(Friend::where('friend_id', $currentUserId)
                ->pluck('user_id'))
            ->unique();

        // Get IDs of users that the current user has blocked
        $blockedUserIds = BlockedSuggestion::where('blocked_suggestions.user_id', $currentUserId)
            ->pluck('blocked_user_id');

        // Retrieve all available users excluding the current user, excluded users, and blocked users
        $usersQuery = User::whereNotIn('id', $excludedUserIds)
            ->whereNotIn('id', $blockedUserIds)
            ->where('id', '!=', $currentUserId);

        // Apply username filter if provided
        if ($usernameFilter) {
            $usersQuery->where('display_name', 'like', "%{$usernameFilter}%");
        }

        $users = $usersQuery->orderBy('created_at', 'desc')->paginate($perPage);

        // Attach profile image and mutual friends count
        foreach ($users as $user) {
            $user->profile_image = getSingleMedia($user, 'profile_image', null);

            // Calculate mutual friends
            $userFriendIds = Friend::where('user_id', $user->id)
                ->where('status', FriendController::STATUS_ACCEPTED)
                ->pluck('friend_id')
                ->merge(Friend::where('friend_id', $user->id)
                    ->where('status', FriendController::STATUS_ACCEPTED)
                    ->pluck('user_id'))
                ->unique();

            $currentUserFriendIds = Friend::where('user_id', $currentUserId)
                ->where('status', FriendController::STATUS_ACCEPTED)
                ->pluck('friend_id')
                ->merge(Friend::where('friend_id', $currentUserId)
                    ->where('status', FriendController::STATUS_ACCEPTED)
                    ->pluck('user_id'))
                ->unique();

            // Find mutual friends count
            $mutualFriendsCount = $userFriendIds->intersect($currentUserFriendIds)->count();
            $user->mutual_friends_count = $mutualFriendsCount; // Attach mutual friends count
        }

        // Format the response
        return response()->json([
            'status' => true,
            'message' => 'Users retrieved successfully.',
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
            'data' => $users->items(),
        ], 200);
    }



    // public function allAvailableUsers(Request $request)
    // {
    //     $currentUserId = Auth::id();

    //     // Pagination setting
    //     $perPage = $request->get('per_page', 10);

    //     // Retrieve all available users excluding the current user
    //     $users = User::where('id', '!=', $currentUserId)
    //         ->with(['friends' => function($query) use ($currentUserId) {
    //             $query->where('user_id', $currentUserId)
    //                   ->orWhere('friend_id', $currentUserId);
    //         }])
    //         ->orderBy('created_at', 'desc')
    //         ->paginate($perPage);

    //     // Attach profile image and mutual friends count
    //     foreach ($users as $user) {
    //         $user->profile_image = getSingleMedia($user, 'profile_image', null);

    //         // Attach the request_by_current_user field if available
    //         $friendRelation = $user->friends->first();
    //         $user->request_by_current_user = $friendRelation ? $friendRelation->request_by_current_user : null;

    //         // Calculate mutual friends
    //         $userFriendIds = Friend::where('user_id', $user->id)
    //             ->where('status', FriendController::STATUS_ACCEPTED)
    //             ->pluck('friend_id')
    //             ->merge(Friend::where('friend_id', $user->id)
    //                 ->where('status', FriendController::STATUS_ACCEPTED)
    //                 ->pluck('user_id'))
    //             ->unique();

    //         $currentUserFriendIds = Friend::where('user_id', $currentUserId)
    //             ->where('status', FriendController::STATUS_ACCEPTED)
    //             ->pluck('friend_id')
    //             ->merge(Friend::where('friend_id', $currentUserId)
    //                 ->where('status', FriendController::STATUS_ACCEPTED)
    //                 ->pluck('user_id'))
    //             ->unique();

    //         // Find mutual friends count
    //         $mutualFriendsCount = $userFriendIds->intersect($currentUserFriendIds)->count();
    //         $user->mutual_friends_count = $mutualFriendsCount; // Attach mutual friends count
    //     }

    //     // Format the response
    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Users retrieved successfully.',
    //         'pagination' => [
    //             'current_page' => $users->currentPage(),
    //             'last_page' => $users->lastPage(),
    //             'per_page' => $users->perPage(),
    //             'total' => $users->total(),
    //         ],
    //         'data' => $users->items(),
    //     ], 200);
    // }



    public function suggestions(Request $request)
    {
        $userId = Auth::id();

        // Get blocked user IDs
        $blockedUserIds = BlockedSuggestion::where('user_id', $userId)
            ->pluck('blocked_user_id')->toArray();

        // Get friends of friends
        $friendIds = Friend::where('user_id', $userId)
            ->where('status', self::STATUS_ACCEPTED)
            ->pluck('friend_id')->toArray();

        $suggestions = User::whereIn('id', function ($query) use ($friendIds) {
            $query->select('friend_id')
                ->from('friends')
                ->whereIn('user_id', $friendIds)
                ->where('status', self::STATUS_ACCEPTED);
        })
            ->where('id', '!=', $userId) // Exclude the authenticated user
            ->whereNotIn('id', $friendIds) // Exclude already friends
            ->whereNotIn('id', $blockedUserIds) // Exclude blocked users
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Friend suggestions retrieved successfully.',
            'data' => $suggestions,
        ], 200);
    }

    // Remove user from suggestions
    public function removeFromSuggestions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()], 422);
        }

        $userId = Auth::id();
        $blockedUserId = $request->user_id;

        // Remove the user from blocked suggestions
        // BlockedSuggestion::where('user_id', $userId)
        //     ->where('blocked_user_id', $blockedUserId)
        //     ->delete();
        BlockedSuggestion::create([
            'user_id' => $userId,
            'blocked_user_id' => $blockedUserId,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'User removed from suggestions.',
        ], 200);
    }

    // Send Friend Request
    public function sendRequest(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()], 422);
        }

        $friendId = $request->user_id;
        $userId = Auth::id();



        // Check if the friend request is invalid
        if ($friendId == $userId || Friend::where('user_id', $userId)->where('friend_id', $friendId)->exists()) {
            return response()->json(['status' => false, 'message' => 'Invalid request.'], 400);
        }
        // Friend::create(['user_id' => $userId, 'friend_id' => $friendId,  'status' => self::STATUS_PENDING]);

        Friend::create(['user_id' => $userId, 'friend_id' => $friendId, 'request_by_current_user' => true, 'status' => self::STATUS_PENDING]);

        return response()->json([
            'status' => true,
            'message' => 'Friend request sent successfully.',
        ], 200);
    }

    // Get all friend requests
    // public function allRequests()
    // {
    //     $userId = Auth::id();


    //     $requests = Friend::where('friend_id', $userId)
    //         ->where('status', self::STATUS_PENDING)
    //         ->get();

    //     foreach ($requests  as $user) {
    //         $user->profile_image = getSingleMedia($user, 'profile_image', null);

    //         // Calculate mutual friends
    //         $userFriendIds = Friend::where('user_id', $user->id)
    //             ->where('status', FriendController::STATUS_ACCEPTED)
    //             ->pluck('friend_id')
    //             ->merge(Friend::where('friend_id', $user->id)
    //                 ->where('status', FriendController::STATUS_ACCEPTED)
    //                 ->pluck('user_id'))
    //             ->unique();

    //         $currentUserFriendIds = Friend::where('user_id',  $userId)
    //             ->where('status', FriendController::STATUS_ACCEPTED)
    //             ->pluck('friend_id')
    //             ->merge(Friend::where('friend_id',  $userId)
    //                 ->where('status', FriendController::STATUS_ACCEPTED)
    //                 ->pluck('user_id'))
    //             ->unique();

    //         // Find mutual friends count
    //         $mutualFriendsCount = $userFriendIds->intersect($currentUserFriendIds)->count();
    //         $user->mutual_friends_count = $mutualFriendsCount; // Attach mutual friends count
    //     }

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Friend requests retrieved successfully.',
    //         'data' => $requests,
    //     ], 200);
    // }

    public function allRequests(Request $request)
    {
        $userId = Auth::id();
    
        // Optional username filter
        $usernameFilter = $request->input('username');
    
        // Get pending friend requests
        $requestsQuery = Friend::where('friend_id', $userId)
            ->where('status', self::STATUS_PENDING)
            ->with('user'); // Eager load the user who sent the request
    
        // Apply username filter if provided
        if ($usernameFilter) {
            $requestsQuery->whereHas('user', function ($query) use ($usernameFilter) {
                $query->where('username', 'like', "%{$usernameFilter}%");
            });
        }
    
        // Determine pagination parameters
        $perPage = $request->input('per_page', 15); // Default to 15 items per page
        $page = $request->input('page', 1); // Default to page 1
    
        // Paginate the friend requests
        $requests = $requestsQuery->paginate($perPage, ['*'], 'page', $page);
    
        // Prepare response data
        $requests->getCollection()->transform(function ($request) use ($userId) {
            // Get the user who sent the request
            $requestingUser = User::find($request->user_id);
    
            // Get mutual friends count
            $requesterFriends = Friend::where('user_id', $request->user_id)
                ->where('status', self::STATUS_ACCEPTED)
                ->pluck('friend_id')
                ->toArray();
    
            $userFriends = Friend::where('user_id', $userId)
                ->where('status', self::STATUS_ACCEPTED)
                ->pluck('friend_id')
                ->toArray();
    
            // Calculate mutual friends by finding the intersection of two arrays
            $mutualFriendsCount = count(array_intersect($requesterFriends, $userFriends));
    
            // Attach the requesting user's data and mutual friends count to the response
            return [
                'id' => $request->id,
                'requesting_user' => [
                    'id' => $requestingUser->id,
                    "username" => $requestingUser->username,
                    "first_name" => $requestingUser->first_name,
                    "last_name" => $requestingUser->last_name,
                    "email" => $requestingUser->email,
                    "phone_number" => $requestingUser->phone_number,
                    'profile_image' => getSingleMedia($requestingUser, 'profile_image', null), // Include profile image
                    'mutual_friends_count' => $mutualFriendsCount,
                ],
            ];
        });
    
        return response()->json([
            'status' => true,
            'message' => 'Friend requests retrieved successfully.',
            'data' => $requests->items(), // Return the actual paginated items
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ], 200);
    }
    



    // Confirm Friend Request
    public function confirmRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request_id' => 'required|exists:friends,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()], 422);
        }
        $friendRequest = Friend::find($request->request_id);
        $friendRequest->status = self::STATUS_ACCEPTED;
        $friendRequest->save();
        Friend::create(['user_id' => $friendRequest->friend_id, 'friend_id' => $friendRequest->user_id, 'status' => self::STATUS_ACCEPTED]);
        return response()->json([
            'status' => true,
            'message' => 'Friend request confirmed successfully.',
        ], 200);
    }

    // Unfriend a user
    public function unfriend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'friend_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()], 422);
        }

        $userId = Auth::id();

        // Remove the friendship both ways
        Friend::where(function ($query) use ($userId, $request) {
            $query->where('user_id', $userId)->where('friend_id', $request->friend_id);
        })->orWhere(function ($query) use ($userId, $request) {
            $query->where('user_id', $request->friend_id)->where('friend_id', $userId);
        })->delete();

        return response()->json([
            'status' => true,
            'message' => 'Friend removed successfully.',
        ], 200);
    }

    // Get all friends
    // public function allFriends()
    // {
    //     $userId = Auth::id();
    //     $friends = Friend::where(function ($query) use ($userId) {
    //         $query->where('user_id', $userId)->orWhere('friend_id', $userId);
    //     })->where('status', self::STATUS_ACCEPTED)
    //         ->get();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Friends retrieved successfully.',
    //         'data' => $friends,
    //     ], 200);
    // }

    public function allFriends(Request $request)
    {
        \Log::info('Incoming Request:', $request->all());
    
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'name' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1', // Add validation for per_page
            'page' => 'nullable|integer|min:1', // Add validation for page
        ]);
    
        if ($validator->fails()) {
            \Log::error('Validation Errors:', $validator->errors()->toArray());
            return response()->json(['status' => false, 'message' => $validator->errors()], 422);
        }
    
        $userId = $request->user_id;
        $nameFilter = $request->name;
        $authUserId = Auth::id();
    
        // Fetch friends where either user_id or friend_id matches userId
        $friendsQuery = Friend::where(function ($query) use ($userId) {
            $query->where('user_id', $userId)
                  ->orWhere('friend_id', $userId);
        })
        ->where('status', self::STATUS_ACCEPTED);
    
        // Apply name filter if provided
        if ($nameFilter) {
            $friendsQuery->whereHas('friend', function ($query) use ($nameFilter) {
                $query->where('display_name', 'like', "%{$nameFilter}%");
            });
        }
    
        // Determine pagination parameters
        $perPage = $request->input('per_page', 15); // Default to 15
        $page = $request->input('page', 1); // Default to page 1
    
        // Paginate the results
        $friends = $friendsQuery->paginate($perPage, ['*'], 'page', $page);
    
        \Log::info('Retrieved Friends:', $friends->toArray());
    
        $uniqueFriends = [];
    
        foreach ($friends as $friend) {
            $friendId = ($friend->user_id == $userId) ? $friend->friend_id : $friend->user_id;
    
            $user = User::find($friendId);
            if (!$user) {
                \Log::warning('User not found for friendId:', [$friendId]);
                continue;
            }
    
            $mutualFriendsCount = Friend::where(function ($query) use ($userId, $friendId) {
                $query->where('user_id', $userId)
                      ->where('status', self::STATUS_ACCEPTED);
            })
            ->whereIn('friend_id', function ($query) use ($friendId) {
                $query->select('friend_id')
                      ->from('friends')
                      ->where('user_id', $friendId)
                      ->where('status', self::STATUS_ACCEPTED);
            })
            ->count();
    
            $friendship = Friend::where(function ($query) use ($authUserId, $friendId) {
                $query->where('user_id', $authUserId)
                      ->where('friend_id', $friendId);
            })
            ->orWhere(function ($query) use ($authUserId, $friendId) {
                $query->where('user_id', $friendId)
                      ->where('friend_id', $authUserId);
            })
            ->first();
    
            $friendStatus = $friendship ? $friendship->status : 0;
    
            if ($friendStatus == 1) {
                $displayStatus = ($friend->user_id == $authUserId) ? 1 : 2;
            } elseif ($friendStatus == 2) {
                $displayStatus = 3; // Pending
            } elseif ($friendStatus == 3) {
                $displayStatus = 4; // Blocked
            } else {
                $displayStatus = 0; // No valid friendship
            }
    
            $uniqueFriends[] = [
                'id' => $user->id,
                'name' => $user->display_name,
                'email' => $user->email,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone_number' => $user->phone_number,
                'profile_image' => getSingleMedia($user, 'profile_image', null),
                'mutual_friends_count' => $mutualFriendsCount,
                'auth_user_friend_status' => $displayStatus,
            ];
        }
    
        // Ensure unique friends are returned without duplicates
        $uniqueFriends = array_values(array_unique($uniqueFriends, SORT_REGULAR));
        \Log::info('Unique Friends:', $uniqueFriends);
    
        return response()->json([
            'status' => true,
            'message' => 'Friends retrieved successfully.',
            'data' => $uniqueFriends,
            'pagination' => [
                'current_page' => $friends->currentPage(),
                'last_page' => $friends->lastPage(),
                'per_page' => $friends->perPage(),
                'total' => $friends->total(),
            ],
        ], 200);
    }
    













    // Block a user
    public function blockUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()], 422);
        }

        $userId = Auth::id();
        $friendId = $request->user_id;

        // Check if the authenticated user is already a friend
        $friendship = Friend::where(function ($query) use ($userId, $friendId) {
            $query->where('user_id', $userId)
                ->where('friend_id', $friendId);
        })->orWhere(function ($query) use ($userId, $friendId) {
            $query->where('user_id', $friendId)
                ->where('friend_id', $userId);
        })->first();

        // If there's no friendship, return an error
        if (!$friendship) {
            return response()->json(['status' => false, 'message' => 'No friendship exists to block.'], 404);
        }

        // Block the user by setting status to blocked
        $friendship->update(['status' => self::STATUS_BLOCKED]);

        return response()->json([
            'status' => true,
            'message' => 'User blocked successfully.',
        ], 200);
    }


    public function unblockUser(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()], 422);
        }

        $userId = Auth::id();
        $friendId = $request->user_id;

        // Check if the user is blocked
        $blockEntry = Friend::where(function ($query) use ($userId, $friendId) {
            $query->where('user_id', $userId)->where('friend_id', $friendId);
        })
            ->orWhere(function ($query) use ($userId, $friendId) {
                $query->where('user_id', $friendId)->where('friend_id', $userId);
            })
            ->where('status', self::STATUS_BLOCKED)
            ->first();

        // If no block entry found, return a message
        if (!$blockEntry) {
            return response()->json(['status' => false, 'message' => 'User is not blocked.'], 400);
        }

        // Unblock the user by deleting the block entry
        $blockEntry->delete();

        // Now, also delete the reciprocal block entry if it exists
        Friend::where(function ($query) use ($userId, $friendId) {
            $query->where('user_id', $friendId)->where('friend_id', $userId);
        })->delete();

        return response()->json([
            'status' => true,
            'message' => 'User unblocked successfully and any reciprocal blocks removed.',
        ], 200);
    }
    public function blockedUsers(Request $request)
    {
        $userId = Auth::id();
        $username = $request->query('username'); // Get the username from the query parameter
    
        // Start the query
        $query = Friend::where('user_id', $userId)
            ->where('status', self::STATUS_BLOCKED)
            ->with('friendUser');
    
        // If a username is provided, filter the results
        if ($username) {
            $query->whereHas('friendUser', function ($q) use ($username) {
                $q->where('username', 'like', '%' . $username . '%');
            });
        }
    
        // Determine pagination parameters
        $perPage = $request->input('per_page', 15); // Default to 15 items per page
        $page = $request->input('page', 1); // Default to page 1
    
        // Paginate the blocked users
        $blocked = $query->paginate($perPage, ['*'], 'page', $page);
    
        // Add profile image for each blocked user
        foreach ($blocked as $block) {
            $block->friendUser->profile_image = getSingleMedia($block->friendUser, 'profile_image', null);
        }
    
        return response()->json([
            'status' => true,
            'message' => 'Blocked users retrieved successfully.',
            'data' => $blocked->items(), // Return the actual items
            'pagination' => [
                'current_page' => $blocked->currentPage(),
                'last_page' => $blocked->lastPage(),
                'per_page' => $blocked->perPage(),
                'total' => $blocked->total(),
            ],
        ], 200);
    }
    




    // Get list of blocked users
    // public function blockedUsers()
    // {
    //     $userId = Auth::id();
    //     $blocked = Friend::where('user_id', $userId)
    //         ->where('status', self::STATUS_BLOCKED)
    //         ->get();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Blocked users retrieved successfully.',
    //         'data' => $blocked,
    //     ], 200);
    // }





    // public function suggestions()
    // {
    //     $userId = Auth::id();

    //     // Get blocked user IDs
    //     $blockedUserIds = BlockedSuggestion::where('user_id', $userId)
    //         ->pluck('blocked_user_id');

    //     // Get friends of friends
    //     $friendIds = Friend::where('user_id', $userId)
    //         ->where('status', self::STATUS_ACCEPTED)
    //         ->pluck('friend_id');

    //     $suggestions = User::whereIn('id', function ($query) use ($friendIds) {
    //         $query->select('friend_id')
    //             ->from('friends')
    //             ->whereIn('user_id', $friendIds)
    //             ->where('status', self::STATUS_ACCEPTED);
    //     })
    //         ->where('id', '!=', $userId) // Exclude the authenticated user
    //         ->whereNotIn('id', $friendIds) // Exclude already friends
    //         ->whereNotIn('id', $blockedUserIds) // Exclude blocked users
    //         ->get();

    //     return response()->json($suggestions);
    // }



    // // Remove user from suggestions
    // public function removeFromSuggestions(Request $request)
    // {
    //     $request->validate(['user_id' => 'required|exists:users,id']);

    //     $userId = Auth::id();
    //     $blockedUserId = $request->user_id;

    //     // Remove the user from blocked suggestions
    //     BlockedSuggestion::where('user_id', $userId)
    //         ->where('blocked_user_id', $blockedUserId)
    //         ->delete();

    //     return response()->json(['success' => true]);
    // }



    // public function sendRequest(Request $request)
    // {
    //     $request->validate(['user_id' => 'required|exists:users,id']);
    //     $friendId = $request->user_id;
    //     $userId = Auth::id();

    //     if ($friendId === $userId || Friend::where('user_id', $userId)->where('friend_id', $friendId)->exists()) {
    //         return response()->json(['message' => 'Invalid request.'], 400);
    //     }

    //     Friend::create(['user_id' => $userId, 'friend_id' => $friendId, 'status' => self::STATUS_PENDING]);
    //     return response()->json(['success' => true]);
    // }

    // public function allRequests()
    // {
    //     $userId = Auth::id();
    //     $requests = Friend::where('friend_id', $userId)->where('status', self::STATUS_PENDING)->get();
    //     return response()->json($requests);
    // }

    // public function confirmRequest(Request $request)
    // {
    //     $request->validate(['request_id' => 'required|exists:friends,id']);
    //     $friendRequest = Friend::find($request->request_id);

    //     $friendRequest->status = self::STATUS_ACCEPTED;
    //     $friendRequest->save();

    //     Friend::create(['user_id' => $friendRequest->friend_id, 'friend_id' => $friendRequest->user_id, 'status' => self::STATUS_ACCEPTED]);

    //     return response()->json(['success' => true]);
    // }

    // public function unfriend(Request $request)
    // {
    //     $request->validate(['friend_id' => 'required|exists:users,id']);
    //     $userId = Auth::id();

    //     Friend::where(function ($query) use ($userId, $request) {
    //         $query->where('user_id', $userId)->where('friend_id', $request->friend_id);
    //     })->orWhere(function ($query) use ($userId, $request) {
    //         $query->where('user_id', $request->friend_id)->where('friend_id', $userId);
    //     })->delete();

    //     return response()->json(['success' => true]);
    // }

    // public function allFriends()
    // {
    //     $userId = Auth::id();
    //     $friends = Friend::where('user_id', $userId)->where('status', self::STATUS_ACCEPTED)
    //         ->orWhere('friend_id', $userId)->where('status', self::STATUS_ACCEPTED)
    //         ->get();

    //     return response()->json($friends);
    // }

    // public function blockUser(Request $request)
    // {
    //     $request->validate(['user_id' => 'required|exists:users,id']);
    //     $userId = Auth::id();
    //     $friendId = $request->user_id;

    //     Friend::updateOrCreate(
    //         ['user_id' => $userId, 'friend_id' => $friendId],
    //         ['status' => self::STATUS_BLOCKED]
    //     );

    //     return response()->json(['success' => true]);
    // }

    // public function blockedUsers()
    // {
    //     $userId = Auth::id();
    //     $blocked = Friend::where('user_id', $userId)->where('status', self::STATUS_BLOCKED)->get();
    //     return response()->json($blocked);
    // }
    public function userSendRequest(Request $request)
    {
        $userId = Auth::id();
        $friendIds = Friend::where('user_id', $userId)
            ->where('status', 1)
            ->pluck('friend_id');
    
        // Use whereIn to find users with the friend_ids
        $friendsQuery = User::whereIn('id', $friendIds);
    
        // Apply display_name filter if provided
        if ($request->has('display_name')) {
            $displayNameFilter = $request->input('display_name');
            $friendsQuery->where('display_name', 'like', "%{$displayNameFilter}%");
        }
    
        // Determine pagination parameters
        $perPage = $request->input('per_page', 15); // Default to 15 items per page
        $page = $request->input('page', 1); // Default to page 1
    
        // Paginate the friends
        $friends = $friendsQuery->paginate($perPage, ['*'], 'page', $page);
    
        // Map over each friend to add profile_image
        $friends->getCollection()->transform(function ($friend) {
            $friend->profile_image = getSingleMedia($friend, 'profile_image', null);
            return $friend;
        });
    
        return response()->json([
            'status' => true,
            'data' => $friends->items(), // Return the actual paginated items
            'pagination' => [
                'current_page' => $friends->currentPage(),
                'last_page' => $friends->lastPage(),
                'per_page' => $friends->perPage(),
                'total' => $friends->total(),
            ],
        ], 200);
    }
    



    public function userSendRequestCancel(Request $request)
    {
        Log::info('Cancel Friend Request', ['request_data' => $request->all()]);

        try {
            $validatedData = $request->validate([
                'friend_id' => 'required|integer|exists:friends,friend_id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation Error', ['errors' => $e->validator->errors()]);
            return response()->json([
                'status' => false,
                'errors' => $e->validator->errors(),
            ], 422);
        }

        // Get the validated friend ID
        $id = $validatedData['friend_id'];

        // Logic to cancel the friend request
        $cancelled = Friend::where('friend_id', $id)
            ->where('user_id', Auth::id())
            ->where('status', 1)
            ->delete();

        if ($cancelled) {
            return response()->json(['status' => true, 'message' => 'Friend request cancelled successfully.'], 200);
        }

        return response()->json(['status' => false, 'message' => 'Friend request not found or already cancelled.'], 404);
    }

    public function userOtherRequestCancel(Request $request)
    {
        Log::info('Cancel Friend Request', ['request_data' => $request->all()]);

        try {
            $validatedData = $request->validate([
                'friend_id' => 'required|integer|exists:friends,user_id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation Error', ['errors' => $e->validator->errors()]);
            return response()->json([
                'status' => false,
                'errors' => $e->validator->errors(),
            ], 422);
        }

        // Get the validated friend ID
        $id = $validatedData['friend_id'];

        // Logic to cancel the friend request
        $cancelled = Friend::where('user_id', $id)
            ->where('friend_id', Auth::id())
            ->where('status', 1)
            ->delete();

        if ($cancelled) {
            return response()->json(['status' => true, 'message' => 'Friend request cancelled successfully.'], 200);
        }

        return response()->json(['status' => false, 'message' => 'Friend request not found or already cancelled.'], 404);
    }
}
