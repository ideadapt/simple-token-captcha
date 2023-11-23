<?php

/**
 * Customize to your needs
 *
 * @return string
 */
function getLang(): string
{
    return ['de', 'fr'][(time() / 60) % 2];
}

$allowed_cross_origins = array(
    'http://localhost:8002'
);

// Generate your key. Must be 32 bytes long and base64 encoded (with padding and not URL safe).
// E.g. with PHP CLI: php -r "echo sodium_bin2base64(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES), SODIUM_BASE64_VARIANT_ORIGINAL);"
$key = '3wLGwrsT4Rx31U4m9biGdiAVp3cJkCdHHN4LfMjdMNc=';


// ============================================================================

// This implementation is based on
// https://security.stackexchange.com/questions/255621/captcha-w-o-server-side-persistence/255637#255637?newreg=125ac8d1679f45599d3642f4ba32b2bf

// encrypt & auth a message with a given key and a random nonce
function authenticated_encrypt($message, $key): string
{
    $base64mode = SODIUM_BASE64_VARIANT_URLSAFE;

    // pick a random nonce value. this doesn't need to be confidential.
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    // apply authenticated encryption to the message using the nonce and key
    $ciphertext = sodium_crypto_secretbox($message, $nonce, $key);

    // encode the nonce and ciphertext as base64 and concat them with a separator
    $encodedNonce = sodium_bin2base64($nonce, $base64mode);
    $encodedCiphertext = sodium_bin2base64($ciphertext, $base64mode);
    $token = $encodedNonce . '.' . $encodedCiphertext;

    // generate an auth tag for the ciphertext and nonce together.
    // this is important because it prevents tampering with or swapping the nonce
    $authTag = sodium_crypto_auth($token, $key);


    // encode the auth tag as base64 and prepend it with a separator
    $encodedAuthTag = sodium_bin2base64($authTag, $base64mode);
    $token = $encodedAuthTag . '.' . $token;

    // end result is "authtag.nonce.ciphertext"
    return $token;
}

// verify auth and decrypt a message with a given key and a random nonce
function authenticated_decrypt($token, $key): bool|string
{
    try {
        $base64mode = SODIUM_BASE64_VARIANT_URLSAFE;

        // pull the base64 strings apart
        $tokenParts = explode('.', $token);
        // check we got 3 parts
        if (count($tokenParts) != 3) {
            error_log('three parts expect, found ' . count($tokenParts));
            return false;
        }

        // extract the three base64 strings
        $encodedAuthTag = $tokenParts[0];
        $encodedNonce = $tokenParts[1];
        $encodedCiphertext = $tokenParts[2];

        // decode base64 for auth tag and validate that the result was a string
        $authTag = sodium_base642bin($encodedAuthTag, $base64mode);
        if (!is_string($authTag)) {
            error_log('base64 decoding failed');
            return false;
        }

        // reconstruct the authenticated part of the string
        $signedTokenPart = $encodedNonce . '.' . $encodedCiphertext;
        // check that the auth tag matches
        if (sodium_crypto_auth_verify($authTag, $signedTokenPart, $key) !== true) {
            error_log('auth tag did not match');
            return false;
        }

        // decode base64 for nonce and validate that the result was a string
        $nonce = sodium_base642bin($encodedNonce, $base64mode);
        if (!is_string($nonce)) {
            error_log('nonce not decodable');
            return false;
        }

        // decode base64 for ciphertext and validate that the result was a string
        $ciphertext = sodium_base642bin($encodedCiphertext, $base64mode);
        if (!is_string($ciphertext)) {
            error_log('cipher text not decodable');
            return false;
        }

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        return $plaintext;
    } catch (\SodiumException $sx) {
        error_log($sx->getMessage());
        return false;
    }
}

readonly class Config
{
    public function __construct(
        public string $key,
        public int    $expiryInSecs = 3 * 60,
        public string $cookieName = 'simple-token-captcha'
    )
    {
    }
}

$config = new Config(key: sodium_base642bin($key, SODIUM_BASE64_VARIANT_ORIGINAL));

function captchaQuestions($lang)
{
    if ($lang === 'fr') {
        $quests = [
            [
                "Quel mot est une couleur: bien, maison, jaune, neutre?",
                "jaune"
            ],
        ];

        $rWord = CaptchaGen::randomWord();
        $quests[] = [
            "Combien de lettres majuscules sont contenues dans " . $rWord['upperWord'] . "? Écrivez sous forme de chiffre.",
            $rWord['upperCaseCount']
        ];
        $quests[] = [
            "Combien de lettres minuscules sont contenues dans " . $rWord['lowerWord'] . "? Écrivez sous forme de chiffre.",
            $rWord['lowerCaseCount']
        ];

        return $quests;
    }
    if ($lang === 'de') {
        $quests = [
            [
                "Welches Wort ist eine Farbe: Gut, Haus, Gelb, Neutral",
                "Gelb"
            ],
        ];

        $rWord = CaptchaGen::randomWord();
        $quests[] = [
            "Wie viele Grossbuchstaben sind in " . $rWord['upperWord'] . " enthalten? Schreibe als Ziffer.",
            $rWord['upperCaseCount']
        ];
        $quests[] = [
            "Wie viele Kleinbuchstaben sind in " . $rWord['lowerWord'] . " enthalten? Schreibe als Ziffer.",
            $rWord['lowerCaseCount']
        ];

        return $quests;
    } else {
        die("unknown language '$lang' given");
    }
}

class CaptchaGen
{
    public function __construct(
        public string $solution,
        public string $challenge,
    )
    {
    }

    public static function generate($quests): CaptchaGen
    {
        $idx = array_rand($quests);
        return new CaptchaGen(solution: $quests[$idx][1], challenge: $quests[$idx][0]);
    }

    static function randomWord(): array
    {
        $maxlen = 8;
        $uppercases = rand(0, 5);
        $lowercases = rand(0, 5);
        $upper = str_shuffle('ABCDEFGHJKMNOPQRSTUVWXYZ');
        $lower = str_shuffle('abcdefghjkmnopqrstuvwxyz');
        $padUpper = substr(str_shuffle("0123456789abcdefghjkmnopqrstuvwxyz"), 0, $maxlen - $uppercases);
        $padLower = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUFWXYZ"), 0, $maxlen - $lowercases);

        return array(
            'lowerWord' => str_shuffle(substr($lower, 0, $lowercases) . $padLower),
            'upperWord' => str_shuffle(substr($upper, 0, $uppercases) . $padUpper),
            'upperCaseCount' => $uppercases . '',
            'lowerCaseCount' => $lowercases . ''
        );
    }
}

function setCaptchaErrorResponse($resultCode): void
{
    header('content-type: application/json; charset=utf-8');
    echo json_encode(['result' => $resultCode, 'lang' => getLang()]);
}

$key = $config->key;
$expiry = $config->expiryInSecs;
$validFrom = time();
$validUntil = $validFrom + $expiry;


/*
Implemented asynchronous HTTP based flow:
   1. send form with invisible placeholders and empty hidden inputs to client
   2. fetch token and challenge once, inject them to every form containing a simple-captcha-token hidden input field
   3. prompt user with challenge on form submit
   4. send user response and token via HTTP to server
   5. validate user response and token on server, respond to client
   6. if ok: perform regular form submit (default function)
      if nok: client stores validation error, reloads page and renders validation error (default functions)
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_REQUEST['simple-captcha-response'])) {

    if (in_array($_SERVER['HTTP_ORIGIN'], $allowed_cross_origins) === true) {
        header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
        header("Access-Control-Allow-Credentials: false");
        header('Access-Control-Allow-Headers: *');
    }

    // validate challenge response submission
    $userCaptchaAnswer = $_REQUEST['simple-captcha-response'];
    $encryptedCaptchaToken = $_REQUEST['simple-captcha-token'];

    // attempt to authenticate and decrypt the token
    $tokenKey = $config->key;
    $captchaToken = authenticated_decrypt(($encryptedCaptchaToken), $tokenKey);

    if ($captchaToken === false) {
        error_log('failed to authenticate or decrypt the token');
        setCaptchaErrorResponse('validation-failed');
        exit;
    }

    // check we got the right data in the token
    $tokenData = json_decode($captchaToken, true);
    if (is_null($tokenData)) {
        error_log('invalid JSON');
        setCaptchaErrorResponse('validation-failed');
        exit;
    }

    // validation
    if (!array_key_exists("solution", $tokenData) ||
        !array_key_exists("client_ip", $tokenData) ||
        !array_key_exists("valid_from", $tokenData) ||
        !array_key_exists("valid_until", $tokenData)) {
        error_log('missing data in token');
        setCaptchaErrorResponse('validation-failed');
        exit;
    }
    if (!is_string($tokenData["solution"]) ||
        !is_string($tokenData["client_ip"]) ||
        !is_int($tokenData["valid_from"]) ||
        !is_int($tokenData["valid_until"])) {
        error_log('improper data in token');
        setCaptchaErrorResponse('validation-failed');
        exit;
    }

    // are we before the valid_from timestamp?
    if (time() < $tokenData["valid_from"]) {
        error_log('the token is not yet valid, somehow');
        setCaptchaErrorResponse('validation-failed');
        exit;
    }

    // are we after the valid_until timestamp?
    if (time() > $tokenData["valid_until"]) {
        error_log('the token has expired');
        setCaptchaErrorResponse('validation-failed');
        exit;
    }

    if (sodium_memcmp($tokenData["client_ip"], $_SERVER['REMOTE_ADDR']) !== 0) {
        error_log('the token was issued to a different IP');
        setCaptchaErrorResponse('validation-failed');
        exit;
    }

    if (strlen($userCaptchaAnswer) !== strlen($tokenData['solution']) || sodium_memcmp(
            $userCaptchaAnswer, $tokenData["solution"]
        ) !== 0) {
        error_log('user response to CAPTCHA challenge is wrong');

        setCaptchaErrorResponse('wrong-answer');
        exit;
    }

    // if we got this far, everything's OK and the CAPTCHA has been validated
    header('content-type: application/json; charset=utf-8');
    echo json_encode(['result' => 'ok']);
    exit;

} else {
    if (str_ends_with($_SERVER['REQUEST_URI'], 'captcha.api.php')
        && $_SERVER['REQUEST_METHOD'] === 'GET'
        && str_contains(array_change_key_case(apache_request_headers())['accept'], 'application/json')) {

        // generate token
        $lang = getLang();
        $captcha = CaptchaGen::generate(captchaQuestions($lang));

        // generate the contents of the token, which will be used to validate the solution later
        $tokenData = [
            "solution" => $captcha->solution,
            "client_ip" => $_SERVER['REMOTE_ADDR'],
            "valid_from" => $validFrom,
            "valid_until" => $validUntil,
        ];
        $tokenStr = json_encode($tokenData);
        $token = authenticated_encrypt($tokenStr, $key);

        header('content-type: application/json; charset=utf-8');

        if (in_array($_SERVER['HTTP_ORIGIN'], $allowed_cross_origins) === true) {
            header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
            header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
            header("Access-Control-Allow-Credentials: false");
            header('Access-Control-Allow-Headers: *');
        }

        echo json_encode(['token' => $token, 'challenge' => $captcha->challenge, 'lang' => $lang]);
        die(0);
    }
}
