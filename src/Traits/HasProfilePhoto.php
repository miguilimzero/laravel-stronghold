<?php

namespace Miguilim\LaravelStronghold\Traits;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasProfilePhoto
{
    protected int $profilePictureResolution = 256;

    /**
     * Sets the users profile photo from a URL.
     */
    public function setProfilePhotoFromUrl(string $url): void
    {
        file_put_contents($file = sys_get_temp_dir().'/'.uniqid('tmp_profile_photo_', true), file_get_contents($url));

        $this->updateProfilePhoto(new UploadedFile(
            path: $file,
            originalName: pathinfo($url)['basename'],
            test: true // This is here to bypass the isValid() method which is invalidating this because because is not really "uploaded"
        ));
    }

    /**
     * Update the user's profile photo.
     */
    public function updateProfilePhoto(UploadedFile $photo): void
    {
        $this->processProfilePhoto($photo);

        tap($this->profile_photo_path, function ($previous) use ($photo) {
            $this->forceFill([
                'profile_photo_path' => $photo->storePublicly(
                    'profile-photos', ['disk' => $this->profilePhotoDisk()]
                ),
            ])->save();

            if ($previous) {
                Storage::disk($this->profilePhotoDisk())->delete($previous);
            }
        });
    }

    /**
     * Delete the user's profile photo.
     */
    public function deleteProfilePhoto(): void
    {
        if ($this->profile_photo_path === null) {
            return;
        }

        Storage::disk($this->profilePhotoDisk())->delete($this->profile_photo_path);

        $this->forceFill([
            'profile_photo_path' => null,
        ])->save();
    }

    /**
     * Get the URL to the user's profile photo.
     */
    public function profilePhotoUrl(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->profile_photo_path
                ? Storage::disk($this->profilePhotoDisk())->url($this->profile_photo_path)
                : $this->defaultProfilePhotoUrl()
        );
    }

    /**
     * Get the default profile photo URL if no profile photo has been uploaded.
     */
    protected function defaultProfilePhotoUrl(): string
    {
        return 'https://www.gravatar.com/avatar/' . md5($this->email) . '?s=' . $this->profilePictureResolution . '&d=retro&r=g';
    }

    /**
     * Get the disk that profile photos should be stored on.
     */
    protected function profilePhotoDisk(): string
    {
        return config('stronghold.profile_photo_disk', 'public');
    }

    /**
     * Process the profile photo with Intervention Image.
     */
    protected function processProfilePhoto(UploadedFile $photo): void
    {
        $manager = new ImageManager(new Driver());

        if ($photo->extension() === 'gif') {
            $manager->read($photo->get())
                ->resize($this->profilePictureResolution, $this->profilePictureResolution)
                ->toGif()
                ->save($photo->getPathname());
        } else {
            $manager->read($photo->get())
                ->resize($this->profilePictureResolution, $this->profilePictureResolution)
                ->toJpeg(90)
                ->save($photo->getPathname());
        }
    }
}
