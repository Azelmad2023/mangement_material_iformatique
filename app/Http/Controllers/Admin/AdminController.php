<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminEmailSesetPassword;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Events\PasswordReset;

class AdminController extends Controller
{
    public function login()
    {
        return view('admin.admin_login');
    }
    public function loginSubmit(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::guard('admin')->attempt($credentials)) {
            $request->session()->regenerate();
            return redirect()->route('admin_dashboard')->with('success', 'Admin LogIn seccessufuly');
        }
        return redirect()->route('login_form')->with('error', 'Invalid Credentials');
    }
    public function dashboard()
    {
        return view('admin.dashboard');
    }
    public function logout()
    {
        Auth::guard('admin')->logout();
        return redirect()->route('login_form')->with('error', 'Logout Seccessufully');
    }

    public function forget_password()
    {
        return view("admin.forget_password");
    }
    // public function forget_password_submit(Request $request)
    // {
    //     $request->validate(['email' => 'required|email']);
    //     $admin = Admin::where('email', $request->email)->first();
    //     if ($admin) {
    //         $token = Hash::make(Str::random(60));
    //         $admin->token = $token;
    //         $admin->update();
    //         // $admin->save();

    //         // $resetLink = route('admin_password_reset', ['token' => $token]);
    //         $resetLink = route('admin_password_reset', ['token' => $token]);
    //         // Redirect to the reset link
    //         return redirect($resetLink);
    //     }
    //     return redirect()->back()->with('error', 'This Email Is Not Exist In The System');
    // }

    public function forget_password_submit(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin) {
            return back()->withErrors(['email' => 'Admin not found']);
        }

        $token = Str::random(60);

        $admin->forceFill([
            'remember_token' => $token,
        ])->save();

        // Send the password reset email
        Mail::to($admin->email)->send(new AdminEmailSesetPassword($token));

        return back()->with('status', 'Password reset link has been sent to your email.');
    }
    public function reset_password_form($token)
    {

        $admin = Admin::where('remember_token', $token)->firstOrFail();
        $email = $admin->email;
        if (!$admin) {
            return redirect()->route('login_form')->with('error', 'Invalid Token or Email');
        }
        return view('admin.reset_password_form', compact('token', 'email'));
    }
    public function reset_password_submit(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'], // Ensure password confirmation
            'token' => ['required'], // Ensure token is present
        ]);

        // Find the admin by email
        $admin = Admin::where('email', $request->email)->first();

        // Check if admin exists and token matches
        if ($admin && $admin->remember_token === $request->token) {
            // Set the new password hash
            $admin->password = Hash::make($request->password);
            $admin->setRememberToken(Str::random(60)); // Regenerate remember token

            // Save the updated admin
            $admin->save();

            // Dispatch the PasswordReset event
            event(new PasswordReset($admin));

            // Redirect to the login form with success message
            return redirect()->route('login_form')->with('status', __('Password has been reset successfully.'));
        }

        // Redirect back with error message if admin not found or token mismatch
        return back()->withErrors(['email' => __('Invalid email or token.')]);
    }
}
