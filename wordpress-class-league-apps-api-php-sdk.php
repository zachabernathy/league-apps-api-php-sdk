<?php
define( 'LEAGUE_APPS_AUTH_HOST', 'https://auth.leagueapps.io/v2/auth/token' );
define( 'LEAGUE_APPS_API_HOST', 'https://public.leagueapps.io' );
define( 'LEAGUE_APPS_SITE_ID', 'YOUR_SITE_ID' );
define( 'LEAGUE_APPS_PRIVATE_KEY_ID', 'YOUR_PRIVATE_KEY_ID' );
define( 'LEAGUE_APPS_PRIVATE_KEY_DIR', get_template_directory() . '/keys/' );

require_once( get_template_directory() . '/vendor/autoload.php' );

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\Converter\StandardConverter;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\KeyManagement\JWKFactory;

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
    $transientName = 'league_apps_token';

    $tokenTransient = get_transient( $transientName );

    if ( false === $tokenTransient ) {
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

      $response = wp_remote_post( $this->authHost . '?' . http_build_query( $postData, NULL, '&' ) );

      $responseCode = wp_remote_retrieve_response_code( $response );

      if ( ! is_wp_error( $response ) && ( 200 === $responseCode || 201 === $responseCode ) ) {
        set_transient( $transientName, $response, time() + 300 );

        $body = json_decode( trim( wp_remote_retrieve_body( $response ) ), true );
      }
    } else {
      $body = json_decode( trim( wp_remote_retrieve_body( $tokenTransient ) ), true );
    }

    return $body ? ( $body->access_token ? $body->access_token : null ) : null;
  }

  public function request( $type, $id = null ) {
    $accessToken = $this->getAccessToken();

    if ( $accessToken ) {
      $transientName = 'league_apps_' . ( $id ? $type . '_' . $id : $type );

      $leagueAppsTransient = get_transient( $transientName );

      switch ( $type ) {
        case 'members':
          $path = '/export/members-2';
          $query = [ 'last-updated' => '0', 'last-id' => '0' ];
          break;

        case 'registrations':
          $path = '/export/registrations-2';
          $query = [ 'last-updated' => '0', 'last-id' => '0' ];
          break;

        case 'member-details':
          $path = '/members/' . $id;
          $query = null;
          break;

        default:
          break;
      }

      if ( false === $leagueAppsTransient ) {
        $urlFull = $query ? $path . '?' . http_build_query( $query, NULL, '&' ) : $path;

        $response = wp_remote_get( $this->apiHost . '/v2/sites/' . $this->siteId . $urlFull, [
          'headers' => [
            'authorization' => 'Bearer ' . $accessToken
          ]
        ]);

        $responseCode = wp_remote_retrieve_response_code( $response );

        if ( ! is_wp_error( $response ) && ( 200 === $responseCode || 201 === $responseCode ) ) {
          set_transient( $transientName, $response, 604800 ); // expire in a week

          $body = json_decode( trim( wp_remote_retrieve_body( $response ) ), true );
        }
      } else {
        $body = json_decode( trim( wp_remote_retrieve_body( $leagueAppsTransient ) ), true );
      }

      return $body ? $body : null;
    }
  }
}

$leagueAppsAPI = new LeagueAppsAPI();
// EXAMPLES
// $registrations = $leagueAppsAPI->request('registrations');
// $members = $leagueAppsAPI->request('members');
// $memberDetails = $leagueAppsAPI->request('member-details', MEMBER_ID_HERE);
