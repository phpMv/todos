<?php

namespace controllers;

use Ajax\semantic\components\validation\Rule;
use Ajax\semantic\html\base\constants\TextAlignment;
use Ajax\semantic\html\collections\HtmlMessage;
use Ajax\semantic\html\elements\HtmlLabel;
use Ajax\semantic\html\elements\HtmlList;
use services\SessionStorageList;
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

	private function showMessage(string $header, string $message, string $type = '', string $icon = 'info circle',array $buttons=[]) {
		$this->loadView('main/vMessage.html', compact('header', 'type', 'icon', 'message','buttons'));
	}

	private function displayList(array $list) {
		if(\count($list)>0){
			$this->jquery->show('._saveList','','',true);
		}
		$title=$this->service->getTitle();
		$js = 'let $item=$(event.target).closest("div.item");$item.children(".ui.checkbox").toggle();$item.children("form").toggle();';
		$this->jquery->click('._toEdit', $js,true,true);
		$this->jquery->change('#multiple','$("._form").toggle();',false,false,false);
		$this->jquery->dblclick('._element', $js,true,true);
		$this->jquery->renderView('TodosController/displayList.html', compact('list','title'));
	}

	public function initialize() {
		parent::initialize();
		$this->service=new SessionStorageList($this);
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
		$frm=$this->jquery->semantic()->dataForm('frm',['title'=>'Création de Nouveau compte','email'=>'','password'=>'','password-conf'=>'','submit'=>'']);
		$frm->setFields(["title",'email',"password",'password-conf',"submit"]);
		$frm->setCaptions(['Entrez vos identifiants','Email','Mot de passe','Confirmation de mot de passe','Créer le compte']);
		$frm->fieldAsMessage('title',['icon'=>'info circle']);
		$frm->setInverted(true);
		$frm->fieldAsInput('email',['rules'=>['email',['type'=>'checkEmail','message'=>"L'email {value} existe déjà."]]]);
		$frm->fieldAsInput('password',['rules'=>'password','inputType'=>'password']);
		$frm->fieldAsInput('password-conf',['rules'=>'match[password]','inputType'=>'password']);
		$frm->fieldAsSubmit('submit','black inverted fluid',Router::path('todos.createAccountSubmit'),'.ui.container',['ajax'=>['hasLoader'=>'internal-x']]);
		$frm->setValidationParams(["on"=>"blur","inline"=>true]);
		$frm->addDividerBefore('email','');
		$frm->addDividerBefore('submit','');
		$frm->addSeparatorAfter('email');
		$this->jquery->exec(Rule::ajax($this->jquery, "checkEmail", Router::path('myAuth.emailExists'), "{}", "result=data.result;", "postForm", [
			"form" => "frm"
		]), true);
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
		$dt=$this->jquery->semantic()->dataTable('dt',CacheFile::class,$files);
		$dt->setFields(['name','elements']);
		$dt->setCaptions(['Identifiant','Eléments']);
		$dt->setValueFunction('name',function($value,$o) use($ku){
			$v=basename($value);
			$lbl= new HtmlLabel($v,$v,'tasks');
			$lbl->setProperty('data-ajax',rawurlencode($ku.DS.$v));
			return $lbl->addClass('_edit basic large inverted');
		});
		$dt->setValueFunction('elements',function($value,$inst){
			$name=$inst->getName();
			$list=CacheManager::$cache->fetch($name);
			if($list) {
				$lbl = new HtmlLabel('list' . $name, count($list));
				$lbl->addPopupHtml(new HtmlList('', $list));
				return $lbl->addClass('basic circular');
			}
			return '';
		});
		$dt->setIdentifierFunction(function($i,$o) use($ku){
			$v=\basename($o->getName());
			return rawurlencode($ku.'[DS]'.$v);
		});
		$dt->addEditDeleteButtons(true,[],function($bt){$bt->addClass('inverted circular');},function($bt){$bt->addClass('inverted circular');});
		$dt->onPreCompile(function ($dt) {
			$dt->getHtmlComponent()->setColAlignmentFromRight(0, TextAlignment::RIGHT);
		});
		$dt->addClass('compact');
		$msg=new HtmlMessage('msg-empty','Vous pouvez ajouter une nouvelle liste en choisissant <a class="ui basic mini inverted button" href="'.Router::path('todos.new').'">Nouvelle liste</a> puis en sauvegardant la liste créée.');
		$msg->addHeader('Aucune liste');
		$msg->setIcon('info circle');
		$dt->setEmptyMessage($msg);
		$dt->setInverted(true);
		$this->jquery->getOnClick('._delete',Router::path('todos.deleteList',['']),'#response',['attr'=>'data-ajax','hasLoader'=>'internal']);
		$this->jquery->getOnClick('._edit',Router::path('todos.loadList',['']),'#response',['attr'=>'data-ajax','hasLoader'=>'internal-x']);
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
