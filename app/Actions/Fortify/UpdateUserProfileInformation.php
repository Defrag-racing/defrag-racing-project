<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Intervention\Image\Facades\Image;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'mimes:jpeg,png,jpg,gif,webp,bmp', 'max:2048'],
            'country' => ['required', 'string', 'max:5']
        ])->validateWithBag('updateProfileInformation');

        if (isset($input['photo'])) {
            $image = $input['photo'];
            $img = Image::make($image);

            $width = $img->width();
            $height = $img->height();

            $imageName = uniqid() . '.' . $image->getClientOriginalExtension();
            $uploadPath = public_path('storage/profile-photos');

            if ($width > 100 || $height > 100) {
                $image = Image::make($image)->fit(100, 100);
                $image->save($uploadPath . '/' . $imageName);
                $input['photo'] = 'profile-photos/' . $imageName;
            } else {
                $input['photo'] = $image->store('profile-photos', 'public');
            }
        }

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $pattern = '/\^\w/';
            $plainName = preg_replace($pattern, '', $input['name']);

            $user->forceFill([
                'name' => $input['name'],
                'plain_name' => $plainName,
                'email' => $input['email'],
                'country' => $input['country'],
                'profile_photo_path' => $input['photo'] ?? $user->profile_photo_path,
            ])->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $pattern = '/\^\w/';
        $plainName = preg_replace($pattern, '', $input['name']);

        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'plain_name' => $plainName,
            'email_verified_at' => null,
            'country' => $input['country'],
            'profile_photo_path' => $input['photo'] ?? $user->profile_photo_path,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
