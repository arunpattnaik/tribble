<?php
if (!defined('BASEPATH'))
  exit('No direct script access allowed');
  
require APPPATH . '/libraries/REST_Controller.php';

class Auth extends REST_Controller
{

  var $ttl;

  public function __construct()
  {
    parent::__construct();

    $this->ttl->one_day = $this->config->item('api_1_day_cache');
    $this->ttl->one_hour = $this->config->item('api_1_hour_cache');
    $this->ttl->thirty_minutes = $this->config->item('api_30_minutes_cache');
    $this->ttl->ten_minutes = $this->config->item('api_10_minutes_cache');;
  }

  private function _double_hash($str){
    return $this->encrypt->sha1($this->encrypt->sha1($str));
  }

  public function login_post()
  {
    $email = $this->post('email');
    $password = $this->post('password');

    if (!$email)
      $this->response(array('resquest_status' => false, 'message' => lang('E_NO_EMAIL')));
    if (!$password)
      $this->response(array('resquest_status' => false, 'message' => lang('E_NO_PASSWORD')));
              
    // load the auth model
    $this->load->model('Auth_api_model', 'mAuth');
    
    $this->load->library('encrypt');
    
    $login = $this->mAuth->checkUserLogin($email,$this->_double_hash($password));
    if(!$login)
      $this->response(array('request_status' => false, 'message' => $this->lang->line('INV_LOGIN')));

    $this->response(array('request_status' => true, 'user' => $login));
  }

  public function sso_login_post()
  {
    $corp_id = $this->post('corp_id');

    if (!$corp_id)
      $this->response(array('resquest_status' => false, 'message' => lang('E_NO_CORP_ID')));

    // load the auth model
    $this->load->model('Auth_api_model', 'mAuth');

    $login = $this->mAuth->checkUserCorpId( $corp_id );
    if(!$login)
      $this->response(array('request_status' => false, 'message' => $this->lang->line('INV_CORP_ID')));

    $this->response(array('request_status' => true, 'user' => $login));
  }

  public function logout($lb = null)
  {
    $this->session->sess_destroy();
    $redirectUrl = site_url() . "/" . string_to_uri($lb);
    redirect($redirectUrl);
  }
  
  public function session_put(){
    
    $session_data = array(
      'user_id'=>$this->put('user_id'),
      'user_name'=>$this->put('user_name'),
      'user_email'=>$this->put('user_email'),
      'user_avatar'=>$this->put('user_avatar')
    );
    // load the memcached driver
    $this->load->driver('cache');
    //$cachekey = sha1($session_data['user_email']);

    // create the cache key
    $api_methods = $this->config->item('api_methods');
    $cachekey = sha1($api_methods['Auth'][__FUNCTION__]['uri'].$session_data['user_email']);

    if(!$this->cache->memcached->get($cachekey)){
      $this->cache->memcached->save($cachekey,$session_data,$this->ttl->one_day);
      $this->response(array('request_status'=>true,'id'=>$cachekey));
    } else {
      $this->response(array('request_status'=>true,'id'=>$cachekey));
    } 
    
  }
  
  public function session_get(){    
    $id = $this->get('id');
    // load the memcached driver
    $this->load->driver('cache');
    if(!$this->cache->memcached->get($id)){
      $this->response(array('request_status'=>false,'message'=>$this->lang->line('INV_SESSION')));
    } else {
      $metadata = $this->cache->memcached->get_metadata($id);   
      $TTL = (int)floor(($metadata['expire'] - time()) / 60);
      if($TTL < 26)
        $this->cache->memcached->save($id,$metadata['data'],$this->ttl->one_day);                    
      $this->response(array('request_status'=>true,'user'=>$this->cache->memcached->get($id)));
    }     
  }

  public function corporate_get(){    
    $unixname = $this->get('unixname');

    if (!$unixname)
      $this->response(array('resquest_status' => false, 'message' => lang('E_NO_UNIXNAME')));

    // load the memcached driver
    $this->load->driver('cache');
    if(!$this->cache->memcached->get($unixname)){

      // load the auth model
      $this->load->model('Auth_api_model', 'mAuth');

      $login = $this->mAuth->checkUserLoginCorp($unixname);

      if(!$login)
        $this->response(array('request_status' => false, 'message' => $this->lang->line('INV_LOGIN')));

      $this->response(array('request_status' => true, 'user' => $login));

    } else {
      $metadata = $this->cache->memcached->get_metadata($unixname);   
      $TTL = (int)floor(($metadata['expire'] - time()) / 60);
      if($TTL < 26)
        $this->cache->memcached->save($unixname,$metadata['data'],$this->ttl->one_day);  
        $this->response(array('request_status'=>true,'user'=>$this->cache->memcached->get($unixname)));
    }     
  }
  
  public function session_delete(){   
    $id = $this->delete('id');
    // load the memcached driver
    $this->load->driver('cache');
    if(!$this->cache->memcached->get($id)){      
      $this->response(array('request_status'=>false,'message'=>$this->lang->line('INV_SESSION')));
    } else {
      $this->cache->memcached->delete($id);
      $this->response(array('request_status'=>true,'message'=>$this->lang->line('S_SESSION_KILLED')));
    }   
  }

}
?>
