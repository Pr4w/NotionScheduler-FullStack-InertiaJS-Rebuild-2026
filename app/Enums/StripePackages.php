<?php

namespace App\Enums;

enum StripePackages: string
{

    // CASES
    case TIER_1 = 'tier_1';
    case TIER_2 = 'tier_2';
    case TIER_3 = 'tier_3';
    case TIER_4 = 'tier_4';
    case TIER_BETA_USER = 'tier_beta_user';
    case TIER_AFFILIATE = 'tier_affiliate';
    case TIER_TRIAL = 'tier_trial';
    case TIER_CUSTOM_GAVIN = 'tier_custom_gavin';

    // Function
    public function prices(): array {

        return match($this) {
            // CASE - Free tier
            self::TIER_1 => [
                'name' => 'Free',
                'description' => 'For beginners & small-time creators',
                'monthly' => 0,
                'yearly' => 0,
                'saved' => 0
            ],

            // CASE - Basic package
            self::TIER_2 => [
                'name' => 'Basic',
                'description' => 'Perfect to get things started',
                'monthly' => [
                    'price' => 10,
                    // 'stripe' => "price_1P4JwOKEkR852Xithqu8va98" // Test mode
                    'stripe' => 'price_1POi6rKEkR852Xitf4adeg8a'
                ],
                'yearly' => [
                    'price' => 90,
                    // 'stripe' => "price_1P4JvwKEkR852XitNdIVyksU" // Test mode
                    'stripe' => 'price_1POi6rKEkR852XitJCpgP7he'
                ],
                'saved' => 20,
            ],

            // CASE - Advanced package
            self::TIER_3 => [
                'name' => 'Advanced',
                'description' => 'For the pros!',
                'monthly' => [
                    'price' => 25,
                    // 'stripe' => "price_1P4JxBKEkR852XitbJGQeAKd" // Test mode
                    'stripe' => "price_1POi6vKEkR852XitZXm9iVGu"
                ],
                'yearly' => [
                    'price' => 250,
                    // 'stripe' => "price_1P4JxfKEkR852XitOGpM5zcE" // Test mode
                    'stripe' => 'price_1POi6vKEkR852XitUK6v5BFO'
                ],
                'saved' => 50
            ],

            // CASE - Agency package
            self::TIER_4 => [
                'name' => 'Agency',
                'description' => 'For professionals',
                'monthly' => [
                    'price' => 45,
                    // 'stripe' => "price_1P4JxBKEkR852XitbJGQeAKd" // Test mode
                    'stripe' => "price_1Qf017KEkR852XitH05aOZJO"
                ],
                'yearly' => [
                    'price' => 450,
                    // 'stripe' => "price_1P4JxfKEkR852XitOGpM5zcE" // Test mode
                    'stripe' => 'price_1Qf01RKEkR852Xit3xIW02U5'
                ],
                'saved' => 90
            ],

            // CASE - Beta users
            self::TIER_BETA_USER => [
                'name' => 'Early Adopter',
                'description' => "Free membership tier for helping beta-test NotionScheduler"
            ],

            // CASE - Beta users
            self::TIER_AFFILIATE => [
                'name' => 'Affiliate package',
                'description' => "More usage for our affiliates"
            ],

            // CASE - Trial
            self::TIER_TRIAL => [
                'name' => 'Trial',
                'description' => "Try out NotionScheduler for free for 7 days!"
            ],

            // CASE - Custom Gavin package
            self::TIER_CUSTOM_GAVIN => [
                'name' => 'Custom Package for Gavin',
                'description' => "Enjoy the best of NotionScheduler!"
            ],

        };

    }

    // Function
    public function options(): array {

        return match($this) {
            // CASE - Free tier
            self::TIER_1 => [
                'social_accounts' => 2,
                'databases' => 1,
                'post_limit' => true,
                'post_limit_count' => 10,
            ],

            // CASE - Basic package
            self::TIER_2 => [
                'social_accounts' => 10,
                'databases' => 2,
                'post_limit' => false,
            ],

            // CASE - Advanced package
            self::TIER_3 => [
                'social_accounts' => 25,
                'databases' => 6,
                'post_limit' => false
            ],

            // CASE - Agency package
            self::TIER_4 => [
                'social_accounts' => 100,
                'databases' => 10,
                'post_limit' => false
            ],

            // CASE - Beta users
            self::TIER_BETA_USER => [
                'social_accounts' => 25,
                'databases' => 5,
                'post_limit' => false,
            ],

            // CASE - Free trial
            self::TIER_TRIAL => [
                'social_accounts' => 5,
                'databases' => 2,
                'post_limit' => false,
            ],

             // CASE - Free trial
            self::TIER_AFFILIATE => [
                'social_accounts' => 5,
                'databases' => 2,
                'post_limit' => false,
            ],

            // CASE - Free trial
            self::TIER_CUSTOM_GAVIN => [
                'social_accounts' => 2,
                'databases' => 1,
                'post_limit' => true,
                'post_limit_count' => 100,
            ],


        };
    }



}

?>