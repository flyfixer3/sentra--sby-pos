<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Http\Resources\UserCredentialResource;
use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }
    function masuk(Request $request) 
    {
        // dd($request);
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

        // return dd($user);
        return new UserCredentialResource($user);
    }

    // function logout(Request $request)
    // {
    //     auth('sanctum')->user()->currentAccessToken()->delete();

    //     return response()->json([
    //         'message' => 'Logout successfully.'
    //     ], 200);
    // }
    
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

    protected function authenticated(Request $request, $user) {
        if ($user->is_active != 1) {
            Auth::logout();

            return back()->with([
                'account_deactivated' => 'Your account is deactivated! Please contact with Super Admin.'
            ]);
        }

        return redirect()->intended();
    }
}
