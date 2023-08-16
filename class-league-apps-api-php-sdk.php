<?php
define( 'LEAGUE_APPS_AUTH_HOST', 'https://auth.leagueapps.io/v2/auth/token' );
define( 'LEAGUE_APPS_API_HOST', 'https://public.leagueapps.io' );
define( 'LEAGUE_APPS_SITE_ID', 'YOUR_SITE_ID' );
define( 'LEAGUE_APPS_PRIVATE_KEY_ID', 'YOUR_PRIVATE_KEY_ID' );
define( 'LEAGUE_APPS_PRIVATE_KEY_DIR', '/path/to/keys/' );

require_once(THEME_DIR . '/vendor/autoload.php');

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\Converter\StandardConverter;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\KeyManagement\JWKFactory;
use GuzzleHttp\Client;

class LeagueAppsAPI {
  private $authHost = LEAGUE_APPS_AUTH_HOST;
  private $apiHost = LEAGUE_APPS_API_HOST;
  private $siteId = LEAGUE_APPS_SITE_ID;
  private $clientId = LEAGUE_APPS_PRIVATE_KEY_ID;
  private $keyFilePath;

  public function __construct() {
    $this->keyFilePath = LEAGUE_APPS_PRIVATE_KEY_DIR . LEAGUE_APPS_PRIVATE_KEY_ID . '.p12';
  }

  public function getAccessToken() {
    $algorithmManager = new AlgorithmManager([new RS256()]);
    $jwsBuilder = new JWSBuilder($algorithmManager);

    $payload = json_encode([
      'iat' => time(),
      'nbf' => time(),
      'exp' => time() + 300,
      'iss' => $this->clientId,
      'sub' => $this->clientId,
      'aud' => $this->authHost
    ]);

    $key = JWKFactory::createFromPKCS12CertificateFile($this->keyFilePath, 'notasecret');

    $jws = $jwsBuilder
      ->create()
      ->withPayload($payload)
      ->addSignature($key, ['alg' => 'RS256'])
      ->build();

    $serializer = new CompactSerializer();
    $token = $serializer->serialize($jws, 0);

    $postData = [
      'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
      'assertion' => $token,
    ];

    $client = new Client(['base_uri' => $this->authHost]);
    $response = $client->request('POST', '', ['query' => $postData]);

    $response_data = json_decode($response->getBody()->getContents());
    return $response_data->access_token;
  }

  // League Apps -> Registrations
  public function getRegistrations() {
    $accessToken = $this->getAccessToken();

    $client = new Client([
      'base_uri' => $this->apiHost,
      'headers' => ['authorization' => 'Bearer ' . $accessToken]
    ]);

    $query = [
      'last-updated' => '0',
      'last-id' => '0'
    ];

    $response = $client->request('GET', '/v2/sites/' . $this->siteId . '/export/registrations-2', ['query' => $query]);
    return $response->getBody()->getContents();
  }

  // League Apps -> Members
  public function getMembers() {
    $accessToken = $this->getAccessToken();

    $client = new Client([
      'base_uri' => $this->apiHost,
      'headers' => ['authorization' => 'Bearer ' . $accessToken]
    ]);

    $query = [
      'last-updated' => '0',
      'last-id' => '0'
    ];

    $response = $client->request('GET', '/v2/sites/' . $this->siteId . '/export/members-2', ['query' => $query]);
    return $response->getBody()->getContents();
  }

  // League Apps -> Member Details
  public function getMemberDetails($memberId) {
    $accessToken = $this->getAccessToken();

    $client = new Client([
      'base_uri' => $this->apiHost,
      'headers' => ['authorization' => 'Bearer ' . $accessToken]
    ]);

    $response = $client->request('GET', '/v2/sites/' . $this->siteId . '/members/' . $memberId);
    return $response->getBody()->getContents();
  }
}

$leagueAppsAPI = new LeagueAppsAPI();
$registrations = $leagueAppsAPI->getRegistrations();
$members = $leagueAppsAPI->getMembers();
$memberDetails = $leagueAppsAPI->getMemberDetails('MEMBER_ID_HERE');
