<x-mail::message>
# Social media account disconnected

Hi {{$user_data['username']}},

It looks like your {{ ucfirst($account_data['platform']) }} account "{{ $account_data['name']}}" is no longer connected to NotionScheduler. This could be due to you revoking NotionScheduler's access to your accounts, or there could be an issue with your account.

If you still plan on using this social media account with NotionScheduler,  head over to the <a href="{{ url('/app/dashboard') }}">NotionScheduler Dashboard</a> to re-connect it and ensure that your scheduled posts can be posted successfully.

Thanks,

Team NotionScheduler

</x-mail::message>