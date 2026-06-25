<?php

namespace App\Models;

use App\Enums\StripePackages;
use Carbon\Carbon;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

class User extends Authenticatable implements FilamentUser, HasName, MustVerifyEmail
{
    use Billable, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'affiliate_parent',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        // 'is_subscribed',
        'subscription_details',
    ];

    protected $with = [
        'affiliates',
    ];

    protected function subscriptionDetails(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {

                // INIT
                $is_subscribed = false;
                $tier = null;
                $options = [];
                $details = [];

                // Give the free tier by default
                $package = StripePackages::tryFrom('tier_1');

                // If their account is younger then 7 days, give them the trial tier
                $days_since_signup = floor(Carbon::parse($this->created_at)->diffInDays(Carbon::now()));
                if ($days_since_signup < 7) {
                    $package = StripePackages::tryFrom('tier_trial');
                }

                // Give the free package to our beta users
                if ($this->id < 140) {
                    $package = StripePackages::tryFrom('tier_beta_user');
                }
                if (in_array($this->id, [
                    765,
                ])) {
                    $package = StripePackages::tryFrom('tier_affiliate');
                }

                // Custom package for Gavin
                if ($this->id == 388) {
                    $package = StripePackages::tryFrom('tier_custom_gavin');
                }

                // Allow them to add more?
                $find_subscription = $this->subscriptions()->active()->first();
                if ($find_subscription) {
                    $package = StripePackages::tryFrom($find_subscription->type);
                }

                // Add the values
                $is_subscribed = true;
                $tier = $package->value;
                $options = $package->options();
                $details = $package->prices();
                $details = [
                    'name' => $details['name'],
                    'description' => $details['description'],
                ];

                return [
                    'is_subscribed' => $is_subscribed,
                    'tier' => $tier,
                    'options' => $options,
                    'details' => $details,
                ];

            }
        );
    }

    public function isAdmin()
    {
        if ($this->id == 1) {
            return true;
        }

        return false;
    }

    public function isSubscribed()
    {
        return $this->subscription_details['is_subscribed'];
    }

    public function getTier()
    {
        return $this->subscription_details['tier'];
    }

    public function getSubscriptionOptions()
    {
        return $this->subscription_details['options'];
    }

    public function hasPostLimit()
    {
        return $this->subscription_details['options']['post_limit'];
    }

    public function getPostLimit()
    {
        if ($this->hasPostLimit()) {
            return $this->subscription_details['options']['post_limit_count'];
        }

        return false;

    }

    public function getPostsThisMonth()
    {
        return NotionPosts::where('userid', $this->id)
            ->where('status', 'posted')
            ->where('posted_date', '>', now()->subDays(30)->endOfDay())
            ->count();
    }

    public function databases(): HasMany
    {
        return $this->hasMany(NotionDatabases::class, 'userid', 'id');
    }

    public function affiliates(): HasOne
    {
        return $this->hasOne(UserAffiliates::class, 'userid', 'id');
    }

    public function getActiveDatabases()
    {
        return $this->databases()->select(['id'])->where('is_valid', 1)->where('is_active', 1);
    }

    public function getActiveDatabaseIDs()
    {
        return $this->getActiveDatabases()->get()->pluck('id');
    }

    public function getActiveDatabaseCount()
    {
        return $this->getActiveDatabases()->count();
    }

    public function getActiveDatabasesWithSocialCount()
    {
        return $this->getActiveDatabases()->withCount([
            'socials' => function (Builder $query) {
                $query->where('is_active', 1)->where('is_valid', 1);
            },
        ])->get();
    }

    public function getTotalSocialAccountsConnectedToDatabases()
    {
        return $this->getActiveDatabasesWithSocialCount()->sum('socials_count');
    }

    /**
     * FILAMENT STUFF
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();

    }

    public function getFilamentName(): string
    {
        return $this->username;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
