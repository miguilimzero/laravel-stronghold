<?php

namespace Miguilim\LaravelStronghold\Traits;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasProfilePhoto
{
    /**
     * Sets the users profile photo from a URL.
     */
    // public function setProfilePhotoFromUrl(string $url): void
    // {
    //     $name = pathinfo($url)['basename'];
    //     file_put_contents($file = "/tmp/{$name}", file_get_contents($url));
    //     $this->updateProfilePhoto(new UploadedFile($file, $name));
    // }

    /**
     * Update the user's profile photo.
     */
    public function updateProfilePhoto(UploadedFile $photo): void
    {
        $manager = new ImageManager(new Driver());

        if ($photo->extension() === 'gif') {
            $manager->read($photo->get())
                ->resize(128, 128)
                ->toGif()
                ->save($photo->getPathname());
        } else {
            $manager->read($photo->get())
                ->resize(128, 128)
                ->toJpeg(90)
                ->save($photo->getPathname());
        }

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
        if (is_null($this->profile_photo_path)) {
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
        return 'https://www.gravatar.com/avatar/' . md5($this->email) . '?s=128&d=retro&r=g';
    }

    /**
     * Get the disk that profile photos should be stored on.
     */
    protected function profilePhotoDisk(): string
    {
        return config('stronghold.profile_photo_disk', 'public');
    }
}
