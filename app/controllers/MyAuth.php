<?php
namespace controllers;
use Ubiquity\attributes\items\router\Post;
use controllers\auth\files\MyAuthFiles;
use Ubiquity\cache\CacheManager;
use Ubiquity\controllers\auth\AuthFiles;
use Ubiquity\utils\http\UResponse;
use Ubiquity\utils\http\USession;
use Ubiquity\utils\http\URequest;
use Ubiquity\attributes\items\router\Route;

#[Route(path: "/login",inherited: true,automated: true)]
class MyAuth extends \Ubiquity\controllers\auth\AuthController{

	public function initialize() {
		parent::initialize();

		if (! URequest::isAjax()) {
			$this->loadView('@activeTheme/main/vHeader.html');
				$this->loadView("TodosController/menu.html");
		}
	}

	protected function finalizeAuth() {
		if (! URequest::isAjax()) {
			$this->loadView('@activeTheme/main/vFooter.html');
		}
	}

	public function index() {
		parent::index();
		$frm=$this->jquery->semantic()->htmlForm('frm-login');
		$frm->setValidationParams(['on'=>'blur','inline'=>true]);
		$frm->addExtraFieldRules('email',[['type'=>'email','message'=>'Merci de saisir une adresse mail valide']]);
		$frm->addExtraFieldRules('password',[['type'=>'empty','message'=>'Le mot de passe est obligatoire']]);

		echo $this->jquery->compile($this->view);
	}

	public function _displayInfoAsString() {
		return true;
	}

	protected function onConnect($connected) {
		$urlParts=$this->getOriginalURL();
		USession::set($this->_getUserSessionKey(), $connected);
		if(isset($urlParts)){
			$this->_forward(implode("/",$urlParts));
		}else{
			UResponse::header('location','/');
		}
	}

	protected function _connect() {
		if(URequest::isPost()){
			$email=URequest::post($this->_getLoginInputName());
			$k=TodosController::USER_KEY.\md5($email);
			if(CacheManager::$cache->exists($k)){
				$user=CacheManager::$cache->fetch($k);
				if(URequest::password_verify('password',$user['pwd'])){
					return $email;
				}
			}
		}
		return;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Ubiquity\controllers\auth\AuthController::isValidUser()
	 */
	public function _isValidUser($action=null) {
		return USession::exists($this->_getUserSessionKey());
	}

	public function _getBaseRoute() {
		return 'login';
	}
	protected function getFiles(): AuthFiles{
		return new MyAuthFiles();
	}


	#[Post(path: "MyAuth/emailExists",name: "myAuth.emailExists")]
	public function emailExists(){
		UResponse::asJSON();
		$email=URequest::post('email');
		$v=CacheManager::$cache->exists(TodosController::USER_KEY.\md5($email));
		echo json_encode(['result'=>!$v]);
	}

}
