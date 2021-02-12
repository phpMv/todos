<?php

namespace controllers;

use Ajax\php\ubiquity\UIService;
use Ajax\semantic\components\validation\Rule;
use Ajax\semantic\html\base\constants\TextAlignment;
use Ajax\semantic\html\collections\HtmlMessage;
use Ajax\semantic\html\elements\HtmlLabel;
use Ajax\semantic\html\elements\HtmlList;
use services\SessionStorageList;
use services\UITodosService;
use Ubiquity\attributes\items\router\Post;
use Ubiquity\attributes\items\router\Get;
use Ubiquity\attributes\items\router\Route;
use Ubiquity\cache\CacheFile;
use Ubiquity\cache\CacheManager;
use Ubiquity\controllers\auth\AuthController;
use Ubiquity\controllers\auth\WithAuthTrait;
use Ubiquity\controllers\Router;
use Ubiquity\utils\http\URequest;

/**
 * Controller TodosController
 * @property \Ajax\php\ubiquity\JsUtils $jquery
 */
class TodosController extends ControllerBase {
	const USER_KEY='datas/users/';
	use WithAuthTrait;

	private SessionStorageList $service;
	private UITodosService $uiService;

	private function showMessage(string $header, string $message, string $type = '', string $icon = 'info circle',array $buttons=[]) {
		$this->loadView('main/vMessage.html', compact('header', 'type', 'icon', 'message','buttons'));
	}

	private function displayList(array $list) {
		$this->uiService->displayList($list,$this->service->getTitle());
	}

	public function initialize() {
		parent::initialize();
		$this->service=new SessionStorageList($this);
		$this->uiService=new UITodosService($this);
		if(!URequest::isAjax()) {
			$this->loadView("TodosController/menu.html");
		}
	}

	public function isValid($action) {
		if($action==='myLists' || $action==='deleteList'){
			return $this->getAuthController()->_isValidUser($action);
		}
		return parent::isValid($action);
	}


	#[Route('_default', name: 'home')]
	public function index() {
		if ($this->service->listExist()) {
			return $this->displayList($this->service->getList(false));
		}
		$bts=[
			['caption'=>'Créer une Nouvelle liste','url'=>['todos.new',[true]],'class'=>'basic']
		];
		if($this->getAuthController()->_isValidUser()){
			$bts[]=['caption'=>'Mes listes','url'=>['todos.myLists',[]],'class'=>'basic'];
		}
		$this->showMessage('Bienvenue !', "<p>TodoLists permet de gérer des listes diverses et variées...", 'error', 'info circle',$bts);
	}

	#[Get(path: "todos/new/{force}", name: 'todos.new')]
	public function newlist(?bool $force=false) {
		if($force===false && $this->service->listExist()){
			return $this->showMessage('Nouvelle liste','Une liste a déjà été créée. Souhaitez-vous la vider ?','warning','warning circle alternate',[
				['url'=>['Home',[]],'caption'=>'Annuler','class'=>''],
				['url'=>['todos.new',[true]],'caption'=>'Confirmer la création','class'=>'green']
			]);
		}
		$this->showMessage('Nouvelle Liste', "Liste correctement créée.", 'success', 'check square outline');
		$this->displayList($this->service->createList());
	}


	#[Post(path: "todos/add", name: 'todos.add')]
	public function addElement() {
		$toAdd = URequest::post('element');
		$elms = URequest::post('elements');
		if($elms){
			$toAdd=explode("\n",$elms);
		}
		if(isset($toAdd)){
			$list=$this->service->addElements($toAdd);
			return $this->displayList($list);
		}
		$this->displayList($this->service->getList());
	}


	#[Get(path: "todos/delete/{index}", name: 'todos.delete')]
	public function deleteElement(int $index) {
		$list=$this->service->deleteElement($index);
		if (!$this->service->hasError()) {
			$this->showMessage('Suppression', $this->service->getMessage('success'), 'success', 'check square outline');
			return $this->displayList($list);
		}
	}


	#[Post(path: "todos/edit/{index}", name: 'todos.edit')]
	public function editElement($index) {
		$list=$this->service->updateElement($index,URequest::post('element'));
		if (!$this->service->hasError()) {
			$this->showMessage('Modification', $this->service->getMessage('success'), 'success', 'check square outline');
			return $this->displayList($list);
		}
	}


	#[Get('todos/saveList', name: 'todos.save')]
	public function saveList() {
		$id=$this->service->persistentSave($this->getAuthController()->_getActiveUser());
		if($this->service->hasError()){
			$this->showMessage('Sauvegarde', $this->service->getMessage('error'), 'error', 'warning circle');
		}else {
			$this->showMessage('Sauvegarde', $this->service->getMessage('success'), 'success', 'check square outline');
		}
		$this->index();
	}


	#[Get(path: "todos/loadList/{uniqid}", name: 'todos.loadList')]
	public function loadList($uniqid) {
		$uniqid=\str_replace('[DS]',DS,$uniqid);
		if ($list=$this->service->persistentGet($uniqid)){
			$this->showMessage('Chargement', "Liste chargée depuis <b>$uniqid</b>", 'success', 'check square outline');
			$this->displayList($list);
		} else {
			$this->showMessage('Chargement', "La liste d'id <b>$uniqid</b> n'existe pas", 'error', 'frown outline');
		}
	}

	#[Post(path: "todos/loadList/", name: 'todos.loadListPost')]
	public function loadListFromForm() {
		$id=URequest::post('id');
		if($id!=null){
			return $this->loadList($id);
		}
		$this->index();
	}

	//#[Route('{url}', requirements: ['url'=>"(?!admin)|(?!Admin).*?"], priority: -1000)]
	public function p404($url){
		echo "<div class='ui error inverted message'><div class='header'>404</div>The page `$url` you are looking for doesn't exist!</div>";
	}

	protected function getAuthController(): AuthController {
		return $this->_auth??=new MyAuth($this);
	}

	#[Get(path: "todos/createAccount",name: "todos.createAccount")]
	public function createAccount(){
		$this->uiService->createAccount();
		$this->jquery->renderView('TodosController/createAccount.html');
	}


	#[Post(path: "todos/createAccount",name: "todos.createAccountSubmit")]
	public function createAccountSubmit(){
		$email=URequest::post('email');
		$pwd=URequest::password_hash('password');
		CacheManager::$cache->store(self::USER_KEY.md5($email),compact('email','pwd'));
		$this->showMessage('Création de compte',"Votre compte a été crée avec l'adresse {$email}.",'success','info circle',[
			['caption'=>'Se connecter','url'=>Router::path('myAuth.connect')]
		]);
	}


	#[Route(path: "todos/myLists",name: "todos.myLists")]
	public function myLists(){
		$ku=\md5($this->getAuthController()->_getActiveUser());
		$files=CacheManager::$cache->getCacheFiles(SessionStorageList::CACHE_KEY.$ku.'/');
		if(\current($files)->getName()==null){
			$files=[];
		}
		$this->uiService->myLists($files,$ku);
		$this->jquery->renderView('TodosController/myLists.html');
	}


	#[Route(path: "todos/deleteList/{id}/{force}",name: "todos.deleteList")]
	public function deleteList($id,$force=false){
		$id=\str_replace('[DS]',DS,$id);
		$name=\basename($id);
		if($force===false){
			return $this->showMessage('Suppression de liste',"Supprimer la liste d'identifiant $name ?",'warning','warning circle alternate',[
				['url'=>['todos.myLists',[]],'caption'=>'Annuler','class'=>''],
				['url'=>['todos.deleteList',[rawurlencode(str_replace(DS,'[DS]',$id)),true]],'caption'=>'Confirmer la suppression','class'=>'green']
			]);
		}
		CacheManager::$cache->remove(SessionStorageList::CACHE_KEY.$id);
		$this->showMessage('Suppression', "Liste $name correctement supprimée.", 'success', 'check square outline');
		$this->myLists();
	}

}
