<?php

namespace App\Http\Controllers\API\Auth;

use App\Events\SendOTPMail;
use App\Http\Controllers\Controller;
use App\Http\Requests\OTPRequest;
use App\Http\Requests\OTPVerifyRequest;
use App\Http\Requests\PasswordResetRequest;
use App\Models\VerifyManage;
use App\Repositories\UserRepository;
use App\Repositories\VerificationCodeRepository;
use App\Services\SmsGatewayService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class ForgotPasswordController extends Controller
{
    /**
     * Resend OTP to the user's phone.
     *
     * @param  OTPRequest  $request  description
     * @return json description
     */
    public function resendOTP(OTPRequest $request)
    {
        $user = UserRepository::findByPhone($request->phone);

        if (! $user) {
            return $this->json(__('Sorry! No user found with this email/phone.'), [], 422);
        }

        if ($user && $user->is_active) {
            $verifyManage = Cache::rememberForever('verify_manage', function () {
                return VerifyManage::first();
            });

            $type = $request->forgot_password ? $verifyManage?->forgot_otp_type : $verifyManage?->register_otp_type;

            $OTP = null;
            $responseMessage = null;
            $emailOrPhone = null;
            $messageType = $request->forgot_password ? 'Forgot Password' : 'Verification';

            // Create a new verification code
            $verificationCode = VerificationCodeRepository::findOrCreateByContact($user->phone);

            $message = 'Your '.$messageType.' OTP is '.$verificationCode->otp;

            $OTP = $verificationCode->otp;

            $phoneCode = null;
            if ($type == 'phone') {

                try {
                    $phoneNumber = $user->phone;
                    $phoneCode = $request->phone_code ?? $user->phone_code;

                    $response = (new SmsGatewayService)->sendSMS($phoneCode, $phoneNumber, $message);

                    // dd($response);
                } catch (\Exception $e) {
                }
                $responseMessage = 'Your '.$messageType.' code is sent to your phone';
                $emailOrPhone = $phoneCode.$user->phone;
            } elseif ($user->email) {
                try {
                    SendOTPMail::dispatch($user->email, $message);
                } catch (\Throwable $th) {
                }

                $responseMessage = 'Your '.$messageType.' code is sent to your email';
                $emailOrPhone = $user->email;
            }

            return $this->json($responseMessage, [
                'email_or_phone' => $emailOrPhone,
                'phone_code' => $phoneCode,
                'otp' => $OTP,
            ]);
        }

        return $this->json('Sorry, your account is not active', [], 422);
    }

    /**
     * Verify the OTP for the user.
     *
     * @param  OTPVerifyRequest  $request  the request containing the OTP to be verified
     */
    public function verifyOtp(OTPVerifyRequest $request)
    {
        $user = UserRepository::findByPhone($request->phone);

        $verificationCode = VerificationCodeRepository::checkOTP($user->phone, $request->otp);

        if (! $verificationCode) {
            return $this->json('Invalid otp', [], Response::HTTP_BAD_REQUEST);
        }

        if (! $user->phone_verified_at) {
            $user->update(['phone_verified_at' => now()]);
        }

        return $this->json('Otp verified successfully', [
            'token' => $verificationCode->token,
        ]);
    }

    /**
     * Reset the user's password.
     *
     * @param  PasswordResetRequest  $request  The request containing the password reset data
     */
    public function resetPassword(PasswordResetRequest $request)
    {
        $verifyOTP = VerificationCodeRepository::checkByToken($request->token);

        $user = UserRepository::findByPhone($verifyOTP->phone);

        if (! $user) {
            return $this->json('Sorry! No user found with this phone.', [], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        $verifyOTP->delete();

        return $this->json('Password reset successfully');
    }
}
