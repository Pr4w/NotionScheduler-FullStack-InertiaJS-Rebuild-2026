<x-mail::message>
# End of your NotionScheduler Trial

Hi {{$user_data['username']}},

Your NotionScheduler trial has expired. You'll still be able to use some of the features, but the number of social media accounts & databases you can concurrently manage has been reduced. It should be fine for most basic use cases, but if you're a more advanced users, feel free to check out <a href="https://app.notionscheduler.app/pricing">our different pricing options</a>.

@if($has_too_many_socials)
It looks like you had {{ $active_socials }} connected social accounts, however your trial package only enables you to run {{ $max_socials }}. Some of those accounts have been automatically disabled for you to reach your quota.
@endif

Thanks,

Team NotionScheduler

</x-mail::message>