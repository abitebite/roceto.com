<?php

namespace App\Http\Controllers\Auth;

use App\Model\User;
use App\Model\Role;
use Validator;
use Session;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;
use Redirect;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use App\Services\ActivationService;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Registration & Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users, as well as the
    | authentication of existing users. By default, this controller uses
    | a simple trait to add these behaviors. Why don't you explore it?
    |
    */

    use AuthenticatesAndRegistersUsers, ThrottlesLogins;

    /**
     * Where to redirect users after login / registration.
     *
     * @var string
     */
    protected $redirectTo = '/';

    protected $activationService;

    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct(ActivationService $activationService)
    {
        $this->middleware($this->guestMiddleware(), ['except' => 'logout']);
          $this->activationService = $activationService;
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|min:6|confirmed',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data)
    {
        $confirmation_code = str_random(30);
        // Create the User and store the object
        $newUser = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'confirmation_code' => $confirmation_code,
        ]);
        //
        // // Add extra data
        $userRole = Role::where('nama', 'User')->first();
        $newUser->role()->attach($userRole->id);
        //
        // $email = $data['email'];
        // Mail::send('emails.verify',
        //     array (
        //     'confirmation_code' => $confirmation_code,
        //   ), function($message) use($email){
        //     $message->to($email)->from('rocetomazzido@gmail.com')->subject('Verify your email address!');
        //   }
        // );
        //
        return $newUser;

        // Session::flash('message', 'Thanks for signing up! Please check your email.');

        // return redirect('/');
    }

    public function register(Request $request)
    {
        $validator = $this->validator($request->all());

        if ($validator->fails()) {
            $this->throwValidationException(
                $request, $validator
            );
        }

        $user = $this->create($request->all());

        $this->activationService->sendActivationMail($user);

        return redirect('login')->with('status', 'Thanks for signing up! We sent you an activation code. Please check your email.');
    }

    public function authenticated(Request $request, $user)
    {
        if (!$user->activated) {
            $this->activationService->sendActivationMail($user);
            auth()->logout();
            return back()->with('warning', 'You need to confirm your account. We have sent you an activation code, please check your email.');
        }
        return redirect()->intended($this->redirectPath());
    }

    public function activateUser($token)
    {
        if ($user = $this->activationService->activateUser($token)) {
            $this->activationService->sendWelcomeMail($user);
            auth()->login($user);
            return redirect($this->redirectPath());
        }
        abort(404);
    }

    public function getLogout()
    {
        $this->auth->logout();
        Session::flush();
        return redirect('/');
    }
}
