<?php

namespace Shoresy;

require 'vendor/autoload.php';

use GuzzleHttp\Client as Guzzle;

header('Access-Control-Allow-Origin: *');
if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
    // Handle CORS pre-flight requests.
    echo 'OK';
    exit();
}

if ('POST' !== $_SERVER['REQUEST_METHOD']) {
    header('HTTP/1.1 405 Bad Method');
    echo '405 Bad Method';
    exit();
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

function verifyRequest(
    string $timestamp,
    string $body,
    string $signingSecret,
    string $signature
): bool {
    // TODO: Verify the timestamp is within 5 minutes of now.
    $baseString = sprintf(
        '%s:%s:%s',
        'v0', // version identifier is always v0 for now.
        $timestamp,
        $body
    );
    $hash = 'v0=' . hash_hmac('sha256', $baseString, $signingSecret);
    return hash_equals($hash, $signature);
}

$token = 'xoxb-2186724946-2064595360421-UQC5GiyTcwTLEraOTglhTiQ1';
$clientSecret = '20a932f171c761be8ac4f2d357f29376';
$signingSecret = '1635838d5859deae1300d9b3f63d931a';

$headers = getallheaders();
$signature = $headers['X-Slack-Signature'];
$timestamp = $headers['X-Slack-Request-Timestamp'];
$handle = fopen('php://input', 'r');
$body = stream_get_contents($handle);
fclose($handle);

if (!verifyRequest($timestamp, $body, $signingSecret, $signature)) {
    header('HTTP/1.1 403 Forbidden');
    echo '403 Forbidden, hash mismatch';
    exit();
}

$body = json_decode($body, false);
if ('url_verification' === $body->type) {
    header('Content-Type: text/plain');
    echo $body->challenge;
    exit();
}

if ('event_callback' !== $body->type) {
    header('HTTP/1.1 501 Not Implemented');
    echo '501 Not Implemented, unhandled event type';
    error_log('Unhandled event type: ' . $body->type);
    exit();
}

$event = $body->event;
if ('app_mention' !== $event->type) {
    error_log('Unhandled callback type: ' . $event->type);
    exit();
}

$text = strtolower($event->text);
if (!str_contains($text, 'fuck') && !str_contains($text, 'you')) {
    // Not an insult.
    exit();
}

$insults = [
    'Fuck you, %s! You’re made of spare parts, aren’t you, bud?',
    'Fuck you, %s! I wish you weren’t so fuckin’ awkward, bud.',
    'Fuck you, %s! Your life’s so fucking pathetic, I ran a charity 15k to raise awareness for it.',
    'Fuck you, %s! Your mom molested me two Halloweens ago, shut the fuck up or I’m taking it to Twitter.',
    'Fuck you, %s! Your life is so pathetic I get a charity tax break just by hanging around you!',
    'Fuck you, %s! Your mom just liked my Instagram post from two years ago in Puerto Vallarta. Tell her I’ll put my swim trunks on for her any time she likes.',
    'Fuck you, %s! I see the muscle shirt came today. Muscles coming tomorrow? Did ya get a tracking number? Oh, I hope he got a tracking number. That package is going to be smaller than the one you’re sportin’ now.',
    'Fuck you, %s! Give yer balls a tug!',
    'Fuck you, %s! I made your mom so wet, Trudeau had to deploy a 24-hour national guard unit to stack sandbags around my bed.',
    'Fuck you, %s! Your mom tried to stick her finger in my bum, but I said I only let Jonesy’s do that.',
    'Fuck you, %s! Your mom ugly cried because she left the lens cap on the camcorder last night.',
    'Fuck you, %s! Tell your mom to top up the cell phone she bought me so I can FaceTime her late night.',
    'Fuck you, %s! I made your mom cum so hard that they made a Canadian heritage minute out of it and Don McKellar played my dick.',
    'Fuck you, %s! Your mom shot cum straight across the room and killed my Siamese fighting fish, threw off the pH levels in my aquarium.',
    '%s, I made an oopsie, can you tell your mom to pick up Jonesy’s mom on the way over to my place? I double booked them by mistake, you fuckin’ loser.',
    'Fuck you, %s, tell your mom I drained the bank account she set up for me. Top it up so I can get some fucking KFC.',
    'Fuck you, %s, your breath’s so bad it gave me an existential crisis — it made me question my whole life.',
    'Fuck you, %s! Tell your mom to leave me alone, she’s been laying on my waterbed since Labour Day.',
    'Fuck you, %s! Shoulda heard your mom last night, she sounded like my great aunt when I pop in for a surprise visit, like, ‘Oooh!’',
    'Fuck you, %s! Your life’s so pathetic, I get a Canadian tax credit just for spending time with you, ya fuckin’ loser!',
];

$url = 'https://slack.com/api/chat.postMessage';
$channel = $event->channel;
$team = $event->team;
$user = sprintf('<@%s>', $event->user);

$insult = sprintf($insults[random_int(0, count($insults) - 1)], $user);
$guzzle = new Guzzle();
$response = $guzzle->request(
    'POST',
    $url,
    [
        'json' => [
            'channel' => $channel,
            'text' => $insult,
        ],
        'headers' => [
            'Content-Type' => 'application/json; charset=utf-8',
            'Authorization' => 'Bearer ' . $token,
        ],
    ]
);

error_log($response->getStatusCode());
error_log((string)$response->getBody());
