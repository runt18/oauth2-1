<?php
/**
 * @copyright  Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

use Joomla\OAuth2\Client;
use Joomla\Registry\Registry;
use Joomla\Input\Input;
use Joomla\Http\Http;
use Joomla\Test\WebInspector;

/**
 * Test class for Client.
 *
 * @since  1.0
 */
class ClientTest extends PHPUnit_Framework_TestCase
{
	/**
	 * @var    Registry  Options for the Client object.
	 */
	protected $options;

	/**
	 * @var    Http  Mock client object.
	 */
	protected $client;

	/**
	 * @var    Input  The input object to use in retrieving GET/POST data.
	 */
	protected $input;

	/**
	 * @var    \Joomla\Application\AbstractWebApplication  The application object to send HTTP headers for redirects.
	 */
	protected $application;

	/**
	 * @var    Client  Object under test.
	 */
	protected $object;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 *
	 * @return  void
	 */
	protected function setUp()
	{
		parent::setUp();

		$_SERVER['HTTP_HOST'] = 'mydomain.com';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
		$_SERVER['REQUEST_URI'] = '/index.php';
		$_SERVER['SCRIPT_NAME'] = '/index.php';

		$this->options = new Registry;
		$this->http = $this->getMockBuilder('Joomla\Http\Http')->getMock();
		$array = array();
		$this->input = new Input($array);
		$this->application = new WebInspector;
		$this->object = new Client($this->options, $this->http, $this->input, $this->application);
	}

	/**
	 * Tests the auth method
	 *
	 * @group   JOAuth2
	 * @return  void
	 */
	public function testAuth()
	{
		$this->object->setOption('authurl', 'https://accounts.google.com/o/oauth2/auth');
		$this->object->setOption('clientid', '01234567891011.apps.googleusercontent.com');
		$this->object->setOption('scope', array('https://www.googleapis.com/auth/adsense', 'https://www.googleapis.com/auth/calendar'));
		$this->object->setOption('redirecturi', 'http://localhost/oauth');
		$this->object->setOption('requestparams', array('access_type' => 'offline', 'approval_prompt' => 'auto'));
		$this->object->setOption('sendheaders', true);

		$this->object->authenticate();
		$this->assertEquals(0, $this->application->closed);

		$this->object->setOption('tokenurl', 'https://accounts.google.com/o/oauth2/token');
		$this->object->setOption('clientsecret', 'jeDs8rKw_jDJW8MMf-ff8ejs');
		$this->input->set('code', '4/wEr_dK8SDkjfpwmc98KejfiwJP-f4wm.kdowmnr82jvmeisjw94mKFIJE48mcEM');

		$this->http->expects($this->once())->method('post')->will($this->returnCallback('encodedGrantOauthCallback'));
		$result = $this->object->authenticate();
		$this->assertEquals('accessvalue', $result['access_token']);
		$this->assertEquals('refreshvalue', $result['refresh_token']);
		$this->assertEquals(3600, $result['expires_in']);
		$this->assertLessThanOrEqual(1, time() - $result['created']);
	}

	/**
	 * Tests the auth method with JSON data
	 *
	 * @group   JOAuth2
	 * @return  void
	 */
	public function testAuthJson()
	{
		$this->object->setOption('tokenurl', 'https://accounts.google.com/o/oauth2/token');
		$this->object->setOption('clientsecret', 'jeDs8rKw_jDJW8MMf-ff8ejs');
		$this->input->set('code', '4/wEr_dK8SDkjfpwmc98KejfiwJP-f4wm.kdowmnr82jvmeisjw94mKFIJE48mcEM');

		$this->http->expects($this->once())->method('post')->will($this->returnCallback('jsonGrantOauthCallback'));
		$result = $this->object->authenticate();
		$this->assertEquals('accessvalue', $result['access_token']);
		$this->assertEquals('refreshvalue', $result['refresh_token']);
		$this->assertEquals(3600, $result['expires_in']);
		$this->assertLessThanOrEqual(1, time() - $result['created']);
	}

	/**
	 * Tests the isauth method
	 *
	 * @group   JOAuth2
	 * @return  void
	 */
	public function testIsAuth()
	{
		$this->assertEquals(false, $this->object->isAuthenticated());

		$token['access_token'] = 'accessvalue';
		$token['refresh_token'] = 'refreshvalue';
		$token['created'] = time();
		$token['expires_in'] = 3600;
		$this->object->setToken($token);

		$this->assertTrue($this->object->isAuthenticated());

		$token['created'] = time() - 4000;
		$token['expires_in'] = 3600;
		$this->object->setToken($token);

		$this->assertFalse($this->object->isAuthenticated());
	}

	/**
	 * Tests the auth method
	 *
	 * @group   JOAuth2
	 * @return  void
	 */
	public function testCreateUrl()
	{
		$this->object->setOption('authurl', 'https://accounts.google.com/o/oauth2/auth');
		$this->object->setOption('clientid', '01234567891011.apps.googleusercontent.com');
		$this->object->setOption('scope', array('https://www.googleapis.com/auth/adsense', 'https://www.googleapis.com/auth/calendar'));
		$this->object->setOption('state', '123456');
		$this->object->setOption('redirecturi', 'http://localhost/oauth');
		$this->object->setOption('requestparams', array('access_type' => 'offline', 'approval_prompt' => 'auto'));

		$url = $this->object->createUrl();
		$expected = 'https://accounts.google.com/o/oauth2/auth?response_type=code';
		$expected .= '&client_id=01234567891011.apps.googleusercontent.com';
		$expected .= '&redirect_uri=http%3A%2F%2Flocalhost%2Foauth';
		$expected .= '&scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fadsense';
		$expected .= '+https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fcalendar';
		$expected .= '&state=123456&access_type=offline&approval_prompt=auto';
		$this->assertEquals($expected, $url);
	}

	/**
	 * Tests the auth method
	 *
	 * @group   JOAuth2
	 * @return  void
	 */
	public function testQuery()
	{
		$token['access_token'] = 'accessvalue';
		$token['refresh_token'] = 'refreshvalue';
		$token['created'] = time() - 1800;
		$token['expires_in'] = 600;
		$this->object->setToken($token);

		$result = $this->object->query('https://www.googleapis.com/auth/calendar', array('param' => 'value'), array(), 'get');
		$this->assertFalse($result);

		$token['expires_in'] = 3600;
		$this->object->setToken($token);

		$this->http->expects($this->once())->method('post')->will($this->returnCallback('queryOauthCallback'));
		$result = $this->object->query('https://www.googleapis.com/auth/calendar', array('param' => 'value'), array(), 'post');
		$this->assertEquals($result->body, 'Lorem ipsum dolor sit amet.');
		$this->assertEquals(200, $result->code);

		$this->object->setOption('authmethod', 'get');
		$this->http->expects($this->once())->method('get')->will($this->returnCallback('getOauthCallback'));
		$result = $this->object->query('https://www.googleapis.com/auth/calendar', array('param' => 'value'), array(), 'get');
		$this->assertEquals($result->body, 'Lorem ipsum dolor sit amet.');
		$this->assertEquals(200, $result->code);
	}

	/**
	 * Tests the setOption method
	 *
	 * @group   JOAuth2
	 * @return  void
	 */
	public function testSetOption()
	{
		$this->object->setOption('key', 'value');

		$this->assertThat(
			$this->options->get('key'),
			$this->equalTo('value')
		);
	}

	/**
	 * Tests the getOption method
	 *
	 * @group   JOAuth2
	 * @return  void
	 */
	public function testGetOption()
	{
		$this->options->set('key', 'value');

		$this->assertThat(
			$this->object->getOption('key'),
			$this->equalTo('value')
		);
	}

	/**
	 * Tests the setToken method
	 *
	 * @group   JOAuth2
	 * @return  void
	 */
	public function testSetToken()
	{
		$this->object->setToken(array('access_token' => 'RANDOM STRING OF DATA'));

		$this->assertThat(
			$this->options->get('accesstoken'),
			$this->equalTo(array('access_token' => 'RANDOM STRING OF DATA'))
		);

		$this->object->setToken(array('access_token' => 'RANDOM STRING OF DATA', 'expires_in' => 3600));

		$this->assertThat(
			$this->options->get('accesstoken'),
			$this->equalTo(array('access_token' => 'RANDOM STRING OF DATA', 'expires_in' => 3600))
		);

		$this->object->setToken(array('access_token' => 'RANDOM STRING OF DATA', 'expires' => 3600));

		$this->assertThat(
			$this->options->get('accesstoken'),
			$this->equalTo(array('access_token' => 'RANDOM STRING OF DATA', 'expires_in' => 3600))
		);
	}

	/**
	 * Tests the getToken method
	 *
	 * @group   JOAuth2
	 * @return  void
	 */
	public function testGetToken()
	{
		$this->options->set('accesstoken', array('access_token' => 'RANDOM STRING OF DATA'));

		$this->assertThat(
			$this->object->getToken(),
			$this->equalTo(array('access_token' => 'RANDOM STRING OF DATA'))
		);
	}

	/**
	 * Tests the refreshToken method
	 *
	 * @group   JOAuth2
	 * @return  void
	 */
	public function testRefreshToken()
	{
		$this->object->setOption('tokenurl', 'https://accounts.google.com/o/oauth2/token');
		$this->object->setOption('clientid', '01234567891011.apps.googleusercontent.com');
		$this->object->setOption('clientsecret', 'jeDs8rKw_jDJW8MMf-ff8ejs');
		$this->object->setOption('redirecturi', 'http://localhost/oauth');
		$this->object->setOption('userefresh', true);
		$this->object->setToken(array('access_token' => 'RANDOM STRING OF DATA', 'expires' => 3600, 'refresh_token' => ' RANDOM STRING OF DATA'));

		$this->http->expects($this->once())->method('post')->will($this->returnCallback('encodedGrantOauthCallback'));
		$result = $this->object->refreshToken();
		$this->assertEquals('accessvalue', $result['access_token']);
		$this->assertEquals('refreshvalue', $result['refresh_token']);
		$this->assertEquals(3600, $result['expires_in']);
		$this->assertLessThanOrEqual(1, time() - $result['created']);
	}

	/**
	 * Tests the refreshToken method with JSON
	 *
	 * @group   JOAuth2
	 * @return  void
	 */
	public function testRefreshTokenJson()
	{
		$this->object->setOption('tokenurl', 'https://accounts.google.com/o/oauth2/token');
		$this->object->setOption('clientid', '01234567891011.apps.googleusercontent.com');
		$this->object->setOption('clientsecret', 'jeDs8rKw_jDJW8MMf-ff8ejs');
		$this->object->setOption('redirecturi', 'http://localhost/oauth');
		$this->object->setOption('userefresh', true);
		$this->object->setToken(array('access_token' => 'RANDOM STRING OF DATA', 'expires' => 3600, 'refresh_token' => ' RANDOM STRING OF DATA'));

		$this->http->expects($this->once())->method('post')->will($this->returnCallback('jsonGrantOauthCallback'));
		$result = $this->object->refreshToken();
		$this->assertEquals('accessvalue', $result['access_token']);
		$this->assertEquals('refreshvalue', $result['refresh_token']);
		$this->assertEquals(3600, $result['expires_in']);
		$this->assertLessThanOrEqual(1, time() - $result['created']);
	}
}

/**
 * Dummy
 *
 * @param   string   $url      Path to the resource.
 * @param   mixed    $data     Either an associative array or a string to be sent with the request.
 * @param   array    $headers  An array of name-value pairs to include in the header of the request
 * @param   integer  $timeout  Read timeout in seconds.
 *
 * @return  object
 *
 * @since   1.0
 */
function encodedGrantOauthCallback($url, $data, array $headers = null, $timeout = null)
{
	$response = new stdClass;

	$response->code = 200;
	$response->headers = array('Content-Type' => 'x-www-form-urlencoded');
	$response->body = 'access_token=accessvalue&refresh_token=refreshvalue&expires_in=3600';

	return $response;
}

/**
 * Dummy
 *
 * @param   string   $url      Path to the resource.
 * @param   mixed    $data     Either an associative array or a string to be sent with the request.
 * @param   array    $headers  An array of name-value pairs to include in the header of the request
 * @param   integer  $timeout  Read timeout in seconds.
 *
 * @return  object
 *
 * @since   1.0
 */
function jsonGrantOauthCallback($url, $data, array $headers = null, $timeout = null)
{
	$response = new stdClass;

	$response->code = 200;
	$response->headers = array('Content-Type' => 'application/json');
	$response->body = '{"access_token":"accessvalue","refresh_token":"refreshvalue","expires_in":3600}';

	return $response;
}

/**
 * Dummy
 *
 * @param   string   $url      Path to the resource.
 * @param   mixed    $data     Either an associative array or a string to be sent with the request.
 * @param   array    $headers  An array of name-value pairs to include in the header of the request
 * @param   integer  $timeout  Read timeout in seconds.
 *
 * @return  object
 *
 * @since   1.0
 */
function queryOauthCallback($url, $data, array $headers = null, $timeout = null)
{
	$response = new stdClass;

	$response->code = 200;
	$response->headers = array('Content-Type' => 'text/html');
	$response->body = 'Lorem ipsum dolor sit amet.';

	return $response;
}

/**
 * Dummy
 *
 * @param   string   $url      Path to the resource.
 * @param   array    $headers  An array of name-value pairs to include in the header of the request.
 * @param   integer  $timeout  Read timeout in seconds.
 *
 * @return  object
 *
 * @since   1.0
 */
function getOauthCallback($url, array $headers = null, $timeout = null)
{
	$response = new stdClass;

	$response->code = 200;
	$response->headers = array('Content-Type' => 'text/html');
	$response->body = 'Lorem ipsum dolor sit amet.';

	return $response;
}
