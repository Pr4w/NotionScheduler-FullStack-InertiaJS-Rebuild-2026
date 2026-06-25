<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

use Illuminate\Support\Facades\Log;

use App\Models\User;

class TrialExpired extends Mailable
{
    use Queueable, SerializesModels;

    public $has_too_many_socials;
    public $active_socials;
    public $max_socials;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public $user
    )
    {

        $active_socials = $user->getTotalSocialAccountsConnectedToDatabases();
        $package = $user->getSubscriptionOptions();
        $this->max_socials = $package['social_accounts'];

        // CASE - Check to see if he has too many social accounts connected
        $this->has_too_many_socials = false;
        if ($this->active_socials > $this->max_socials) {
            $this->has_too_many_socials = true;

            // Disable some of those social accounts for the user
            $posts = \App\Models\NotionPosts::where('userid', $user->id)
                ->get() 
                ->groupBy('account_id')
                ->map
                ->count() // This returns an array of Account_ID -> Number of posts
                ->sortDesc() // Get the ones with the most posts at the top
                ->slice(0, $this->max_socials) // Slice it to only keep the top posters
                ->keys() // Get the Account_IDs only of the top posters
                ->all(); // Convert to array

            Log::info("TrialExpired Mailer...");
            Log::info("Max socials is " . $this->max_socials . " and active socials is " . $this->active_socials . "");
            Log::info("Here are the accounts we want to keep for this guy");
            Log::info($posts);

            $db = \App\Models\NotionSocialAccounts::where('userid', $user->id)
                ->whereNotIn('id', $posts)
                ->get();

            Log::info("Here are the accounts that we should remove");
            Log::info($db);
            Log::info("UNHANDLED, we haven't actually decided to remove them yet, we're still testing");


        }

    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'NotionScheduler - End of your trial period',
            // replyTo: [
            //     new Address($this->user_data['email']),
            // ]
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.trialexpired',
            with: [
                'user_data' => $this->user->toArray(), 
                'has_too_many_socials' => $this->has_too_many_socials,
                'active_socials' => $this->active_socials,
                'max_socials' => $this->max_socials
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}