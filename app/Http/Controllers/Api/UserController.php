<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddShippingAddressRequest;
use App\Http\Requests\FeedRequest;
use App\Http\Resources\InterestsResource;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TagsSimpleResource;
use App\Http\Resources\UserInfoResource;
use App\Http\Resources\UserMilestoneResource;
use App\Http\Resources\UserUploadAvatarResource;
use App\Models\InterestsCategory;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserSettings;
use App\Models\UserShippingAddress;
use App\Notifications\NewSubscription;
use App\Notifications\TestNotificationWithDeepLink;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{

    public function index()
    {
        return InterestsResource::collection(auth()->user()->interests);
    }

    public function assignInterest(Request $request)
    {
        $interest = InterestsCategory::where(['slug' => $request->get('interest')])->first();
        auth()->user()->assignInterest($interest->id);
        return InterestsResource::collection(auth()->user()->interests);
    }

    public function assignInterestArray(Request $request)
    {
        $interests = $request->get('interests');

//        $userInterests = auth()->user()->interests->map(function ($userInterest) {
//            return $userInterest->slug;
//        })->toArray();

//        dd($userInterests);

        auth()->user()->revokeInterests();

        if (is_array($interests)) {
            foreach ($interests as $interest) {
                if ($interest) {
                    $findInterest = InterestsCategory::where(['slug' => $interest])->first();
                    auth()->user()->assignInterest($findInterest->id);
                }
            }
        }
        auth()->user()->refresh();
        return InterestsResource::collection(auth()->user()->interests);
    }

    public function assignNotInterest(Request $request)
    {
        $interest = InterestsCategory::where(['slug' => $request->get('interest')])->first();
        auth()->user()->assignNotInterest($interest->id);
        return InterestsResource::collection(auth()->user()->notInterests);
    }

    public function assignNotInterestArray(Request $request)
    {
        $interests = $request->get('interests');
        auth()->user()->revokeNotInterests();

        if (is_array($interests)) {
            foreach ($interests as $interest) {
                if ($interest) {
                    $findInterest = InterestsCategory::where(['slug' => $interest])->first();
                    auth()->user()->assignNotInterest($findInterest->id);
                }
            }
        }
        auth()->user()->refresh();
        return InterestsResource::collection(auth()->user()->notInterests);
    }

    public function assignTag(Request $request)
    {
        $tag = Tag::where('id', '=', $request->get('tag_id'))->first();
        auth()->user()->attachTag($tag);
        return TagsSimpleResource::collection(auth()->user()->tags);
    }

    public function removeTag(Request $request)
    {
        $tag = Tag::where('id', '=', $request->get('tag_id'))->first();
        auth()->user()->detachTag($tag);
        return TagsSimpleResource::collection(auth()->user()->tags);
    }

    public function userInfo($id)
    {
        $user = User::where(['id' => $id])->first();
        return new UserInfoResource($user);
    }

    /**
     * Get the ids of posts what was updated after last update date (timestamp)
     *
     * @return JsonResponse Example: {"data":[15,16,17,18,19,20,21,22]}
     */
    public function feed(FeedRequest $request)
    {
        $fromDate = Carbon::createFromTimestamp($request->get('last_update_date'))->toDateTimeString();

        $user = auth()->user();

        $posts = Post::where('updated_at', '>=', $fromDate)->where('owner_id', $user->id)->get('id')->map(function (Post $post) {
            return $post->id;
        });

        if (count($posts) == 0) {
            $interests = $user->interests->map(function ($interest) {
                return $interest->id;
            });

            $posts = Post::whereHas('interests', function ($query) use ($interests) {
                return $query->whereIn('interests_category.id', $interests);
            })->limit(50)->get()->map(function (Post $post) {
                return $post->id;
            });
        }

        return response()->json(['data' => $posts]);
    }

    public function userPosts($id)
    {
        $user = User::where(['id' => $id])->first();
        return PostResource::collection($user->load('posts')->posts);
    }

    public function avatarUpload(Request $request)
    {
        $user = auth()->user();
        $user->clearMediaCollection('preview');
        $user->addMediaFromRequest('avatar')->toMediaCollection('preview');

        return response()->json(['data' => new UserUploadAvatarResource($user)]);
    }

    public function subscribe($id)
    {
        $user = User::where(['id' => $id])->first();
        $subscriber = auth()->user();
        $subscriber->subscribe($user);
        $user->notify(new NewSubscription($user, $subscriber));

        return response()->json($subscriber->subscriptions);
    }

    public function unsubscribe($id)
    {
        $user = User::where(['id' => $id])->first();
        $subscriber = auth()->user();
        $subscriber->unsubscribe($user);

        return response()->json($subscriber->subscriptions);
    }

    public function subscriptions()
    {
        $user = auth()->user();
        return response()->json(['data' => $user->subscriptions]);
    }

    public function subscribers()
    {
        $user = auth()->user();
        return response()->json(['data' => $user->subscribers]);
    }

    public function milestones(Request $request)
    {
        return response()->json(['data' => new UserMilestoneResource(auth()->user())]);
    }

    public function addShippingAddress(AddShippingAddressRequest $shippingAddressRequest)
    {

        $user = auth()->user();
        $shippingAddressRequest->merge(['user_id' => $user->id]);
        $shippingAddress = UserShippingAddress::create($shippingAddressRequest->all());

        return response()->json(['data' => $shippingAddress]);
    }

    public function setToken(Request $request)
    {
        $user = auth()->user();
        $user->pushToken = $request->get('pushToken');
        $user->save();

        return response()->json(['data' => $user]);
    }

    public function getNotificationsList(Request $request) {
        $notifications = auth()->user()->notifications;

        return response()->json(['data' => NotificationResource::collection($notifications)]);
    }

    public function marketLike($id) {
        $user = User::where(['id' => $id])->first();
        $profile = $user->profile;
        $like = auth()->user()->like($profile);
        return response()->json(['data' => $like]);
    }

    public function marketUnlike($id) {
        $user = User::where(['id' => $id])->first();
        $profile = $user->profile;
        $like = auth()->user()->unlike($profile);
        return response()->json(['data' => $like]);
    }

    public function setupSettings(Request $request) {
        $user = auth()->user();
        if ($user->setting) {
            $user->setting->update($request->only('followersHidden','followedHidden'));
            $setting = $user->setting;
        } else {
            $setting = new UserSettings();
            $setting->user_id = $user->id;
            $setting->followersHidden = $request->get('followersHidden');
            $setting->followedHidden = $request->get('followedHidden');
            $setting->save();
        }

        return response()->json(['data' => $setting]);
    }
}
