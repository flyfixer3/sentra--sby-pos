<?php

namespace App\Http\Controllers;

use App\Http\Helpers\Helper;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ChangeTcPasswordRequest;
use App\Http\Resources\UserCredentialResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LoginAPIController extends Controller
{
    //
    function masuk(Request $request) 
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->username)->first();

        if (is_null($user) || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'error' => ['Invalid username or password'],
            ]);
        }

        $user['token'] = $user->createToken('api-token')->plainTextToken;
        $user['user_role'] = 'TC_OWNER';

        return new UserCredentialResource($user);
    }

    function logout(Request $request)
    {
        auth('sanctum')->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successfully.'
        ], 200);
    }

    function changePassword(ChangePasswordRequest $request)
    {
        $validatedData = $request->validated();
        $validatedData = Helper::convertToSnakeCase($validatedData);

        $user = Auth::user();

        if (!Hash::check($validatedData['old_password'], $user->password)) {
            return response()->json([
                'message' => 'Wrong current password'
            ], 400);
        }

        $user->password = Hash::make($validatedData['new_password']);
        $user->save();

        return response()->json([
            'message' => 'Change password success.'
        ], 200);
    }

    // IBO function
    function changeTcUserPassword(ChangeTcPasswordRequest $request, $userId)
    {
        $validatedData = $request->validated();
        $validatedData = Helper::convertToSnakeCase($validatedData);

        $targetUser = User::where('id', $userId)
        ->whereNotNull('training_center_id')
        ->first();

        if (is_null($targetUser)) {
            return response()->json([
                'message' => 'User not found'
            ], 400);
        }

        $targetUser->password = Hash::make($validatedData['new_password']);
        $targetUser->save();

        return response()->json([
            'message' => 'Change password success.'
        ], 200);
    }
}
