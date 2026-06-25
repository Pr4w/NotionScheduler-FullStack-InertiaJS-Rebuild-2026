<x-mail::message>
# Support request
## Details
*Username* - {{$user_data['username']}}<br />
*User ID* - {{$user_data['id']}}<br />
*User Email* - {{$user_data['email']}}<br />


## Message

{{ $mail_data }}

</x-mail::message>