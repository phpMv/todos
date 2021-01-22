<?php

namespace controllers;

use Ubiquity\attributes\items\router\Post;
use Ubiquity\attributes\items\router\Get;
use Ubiquity\attributes\items\router\Route;
use Ubiquity\cache\CacheManager;
use Ubiquity\controllers\Router;
use Ubiquity\utils\http\URequest;
use Ubiquity\utils\http\USession;

/**
 * Controller TodosController
 * @property \Ajax\php\ubiquity\JsUtils $jquery
 */
class TodosController extends ControllerBase {
	const KEY = 'datas/lists/';

	private $info;

	private function showMessage(string $header, string $message, string $type = '', string $icon = 'info circle',array $buttons=[]) {
		$this->loadView('main/vMessage.html', compact('header', 'type', 'icon', 'message','buttons'));
	}

	private function displayList(array $list, ?int $toEdit = null) {
		if(\count($list)>0){
			$this->jquery->show('._saveList','','',true);
		}
		$title=$this->info['id'];
		if($this->info['updated']){
			$title.=' [*]';
		}
		$js = 'let $item=$(event.target).closest("div.item");$item.children(".ui.checkbox").toggle();$item.children("form").toggle();';
		$this->jquery->click('._toEdit', $js,true,true);
		$this->jquery->change('#multiple','$("._form").toggle();',false,false,false);
		$this->jquery->dblclick('._element', $js,true,true);
		//$this->jquery->getOn('dblclick','._element',Router::path('todos.toEdit'),'body .ui.container',['attr'=>'data-index','hasLoader'=>false]);
		$this->jquery->renderView('TodosController/displayList.html', compact('list', 'toEdit','title'));
	}

	private function getList(): array {
		return USession::get('list', []);
	}

	private function setList(array $list): void {
		$this->listUpdated(true);
		USession::set('list', $list);;
	}

	private function listUpdated(bool $updated=false):void{
		$info=USession::get('active-list',['id'=>'not saved']);
		$this->setInfo($info['id'],$updated);
	}

	private function setInfo(string $id=null,bool $updated=false){
		$this->info=['id'=>$id??'not saved','updated'=>$updated];
		if(isset($id)) {
			USession::set('active-list', $this->info);
		}
	}

	public function getUniqid(){
		return ($this->info['id']==='not saved')?uniqid('',true):$this->info['id'];
	}

	private function getInfo(){
		$this->info=USession::get('active-list',['id'=>'not saved']);
	}

	public function initialize() {
		parent::initialize();
		$this->loadView("TodosController/menu.html");
	}

	private function createCopyButton(string $bt,string $elmText){
		$this->jquery->click($bt,'let $temp = $("<input>");$("body").append($temp);$temp.val($("'.$elmText.'").text()).select();document.execCommand("copy");$temp.remove();$("'.$bt.'").popup({content: "copié!"}).popup("show");');
	}

	private function getButtonUrl($url){
		$this->createCopyButton('#btCopy','#url');
		return '<span id="url" class="ui inverted label">'.$url.'</span>&nbsp;<div class="ui mini circular inverted basic icon button" id="btCopy"><i class="copy icon"></i></div>';
	}

	#[Route('_default', name: 'home')]
	public function index() {
		if (USession::exists('list')) {
			$this->getInfo();
			return $this->displayList(USession::get('list'));
		}
		$this->jquery->renderView("TodosController/index.html");
	}

	#[Get(path: "todos/new/{force}", name: 'todos.new')]
	public function newlist(?bool $force=false) {
		if($force===false && USession::exists('list')){
			return $this->showMessage('Nouvelle liste','Une liste a déjà été créée. Souhaitez-vous la vider ?','warning','warning circle alternate',[
				['url'=>['Home',[]],'caption'=>'Annuler','class'=>''],
				['url'=>['todos.new',[true]],'caption'=>'Confirmer la création','class'=>'green']
			]);
		}
		$list = USession::set('list', []);
		$this->info=['id'=>'not saved','updated'=>false];
		USession::set('active-list',$this->info);
		$this->showMessage('Nouvelle Liste', "Liste correctement créée.", 'success', 'check square outline');
		$this->displayList($list);
	}


	#[Post(path: "todos/add", name: 'todos.add')]
	public function addElement() {
		$elm = URequest::post('element');
		$elms = URequest::post('elements');
		$list = $this->getList();
		if ($elms) {
			$elems=explode("\n",$elms);
			foreach ($elems as $e) {
				if($e!='') {
					$list[] = $e;
				}
			}
		}elseif ($elm){
			$list[] = $elm;
		}
		$this->setList($list);
		$this->displayList($list);
	}


	#[Get(path: "todos/delete/{index}", name: 'todos.delete')]
	public function deleteElement(int $index) {
		$list = $this->getList();
		if (isset($list[$index])) {
			$this->showMessage('Suppression', "L'élément <b>$list[$index]</b> a été supprimé.", 'error', 'check square outline');
			unset($list[$index]);
			$list=\array_values($list);
			$this->setList($list);
		}
		$this->displayList($list);
	}


	#[Post(path: "todos/edit/{index}", name: 'todos.edit')]
	public function edit($index) {
		$list = $this->getList();
		if (isset($list[$index])) {
			$elm = URequest::post('element');
			if ($elm) {
				$list[$index] = $elm;
				$this->setList($list);
			}
		}
		$this->displayList($list);
	}


	#[Get('todos/saveList', name: 'todos.save')]
	public function saveList() {
		$this->getInfo();
		$id = $this->getUniqid();
		$this->setInfo($id);
		CacheManager::$cache->store(self::KEY . $id, $this->getList());
		$this->showMessage('Sauvegarde', "La liste a été sauvegardée sous l'id <b>$id</b> et sera accessible depuis l'url " . $this->getButtonUrl(Router::url('todos.loadList', [$id])), 'success', 'check square outline');
		$this->index();
	}


	#[Get(path: "todos/loadList/{uniqid}", name: 'todos.loadList')]
	public function loadList($uniqid) {
		if (CacheManager::$cache->exists(self::KEY . $uniqid)) {
			$list = CacheManager::$cache->fetch(self::KEY . $uniqid);
			USession::set('list', $list);
			$this->setInfo($uniqid);
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

	#[Route('{url}', priority: -1000)]
	public function p404($url){
		echo "<div class='ui error inverted message'><div class='header'>404</div>The page `$url` you are looking for doesn't exist!</div>";
	}
}
