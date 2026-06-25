<x-mail::message>
# Database disconnected

Hi {{$user_data['username']}},

It looks like one of your NotionScheduler databases has been disconnected from our services. The database was previously located <a href="https://notion.so/{{str_replace('-', '', $database_data['database_id'])}}">here</a>.

The reason it was removed is: {{ $message }}

In order to re-activate your database, head over to the <a href="https://app.notionscheduler.app">NotionScheduler Dashboard</a>. 

Thanks,

Team NotionScheduler

</x-mail::message>