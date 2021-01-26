<?php

namespace controllers;

use services\SessionStorageList;
use Ubiquity\attributes\items\router\Post;
use Ubiquity\attributes\items\router\Get;
use Ubiquity\attributes\items\router\Route;
use Ubiquity\utils\http\URequest;

/**
 * Controller TodosController
 * @property \Ajax\php\ubiquity\JsUtils $jquery
 */
class TodosController extends ControllerBase {

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
		$this->jquery->getHref('a','.ui.container',['hasLoader'=>false,'historize'=>false]);
		$this->jquery->renderView('TodosController/displayList.html', compact('list','title'));
	}

	public function initialize() {
		parent::initialize();
		$this->service=new SessionStorageList($this);
		$this->loadView("TodosController/menu.html");
	}



	#[Route('_default', name: 'home')]
	public function index() {
		if ($this->service->listExist()) {
			return $this->displayList($this->service->getList(false));
		}
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
	public function edit($index) {
		$list=$this->service->updateElement($index,URequest::post('element'));
		if (!$this->service->hasError()) {
			$this->showMessage('Modification', $this->service->getMessage('success'), 'success', 'check square outline');
			return $this->displayList($list);
		}
	}


	#[Get('todos/saveList', name: 'todos.save')]
	public function saveList() {
		$id=$this->service->persistentSave();
		if($this->service->hasError()){
			$this->showMessage('Sauvegarde', $this->service->getMessage('error'), 'error', 'warning circle');
		}else {
			$this->showMessage('Sauvegarde', $this->service->getMessage('success'), 'success', 'check square outline');
		}
		$this->index();
	}


	#[Get(path: "todos/loadList/{uniqid}", name: 'todos.loadList')]
	public function loadList($uniqid) {
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

	#[Route('{url}', priority: -1000)]
	public function p404($url){
		echo "<div class='ui error inverted message'><div class='header'>404</div>The page `$url` you are looking for doesn't exist!</div>";
	}
}
