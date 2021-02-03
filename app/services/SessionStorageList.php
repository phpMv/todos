<?php
namespace services;

use Ubiquity\cache\CacheManager;
use Ubiquity\controllers\Router;
use Ubiquity\exceptions\CacheException;
use Ubiquity\utils\http\USession;


class SessionStorageList{
	const CACHE_KEY = 'datas/lists/';
	const EMPTY_LIST_ID='not saved';
	const LIST_SESSION_KEY='list';
	const ACTIVE_LIST_SESSION_KEY='active-list';

	private array $info;

	private array $message;

	public function __construct($controller){
		$this->jquery=$controller->jquery;
		$this->info=['id'=>self::EMPTY_LIST_ID,'updated'=>false];
		$this->message=[];
	}

	private function createCopyButton(string $bt,string $elmText){
		$this->jquery->click($bt,'
									let $temp = $("<input>");
									$("body").append($temp);
									$temp.val($("'.$elmText.'").text()).select();
									document.execCommand("copy");
									$temp.remove();
									$("'.$bt.'").popup({content: "Copié!"}).popup("show");
							');
	}

	private function getButtonUrl($url){
		$this->createCopyButton('#btCopy','#url');
		return '<span id="url" class="ui inverted label">'.$url.'</span>&nbsp;<div class="ui mini circular inverted basic icon button" id="btCopy"><i class="copy icon"></i></div>';
	}

	public function getList($initialize=true): array {
		return USession::get(self::LIST_SESSION_KEY, $initialize?[]:null);
	}

	public function setList(array $list): void {
		$this->listUpdated(true);
		USession::set(self::LIST_SESSION_KEY, $list);;
	}

	public function listExist():bool{
		return USession::exists(self::LIST_SESSION_KEY);
	}

	public function createList():array{
		$list = USession::set(self::LIST_SESSION_KEY, []);
		$this->setInfo(self::EMPTY_LIST_ID);
		return $list;
	}

	public function listUpdated(bool $updated=false):void{
		$info=USession::get(self::ACTIVE_LIST_SESSION_KEY,['id'=>self::EMPTY_LIST_ID]);
		$this->setInfo($info['id'],$updated);
	}

	public function setInfo(string $id=null,bool $updated=false){
		$this->info=['id'=>$id??self::EMPTY_LIST_ID,'updated'=>$updated];
		if(isset($id)) {
			USession::set(self::ACTIVE_LIST_SESSION_KEY, $this->info);
		}
	}

	private function getUniqid($user){
		$pathExt='';
		if($user!=null){
			$pathExt=\md5($user).'/';
		}
		return ($this->info['id']===self::EMPTY_LIST_ID)?$pathExt.uniqid('',true):$this->info['id'];
	}

	private function getInfo():array{
		return $this->info=USession::get(self::ACTIVE_LIST_SESSION_KEY,['id'=>self::EMPTY_LIST_ID]);
	}

	public function persistentGet(string $id):array|bool{
		if (CacheManager::$cache->exists(self::CACHE_KEY . $id)) {
			$list = CacheManager::$cache->fetch(self::CACHE_KEY . $id);
			USession::set(self::LIST_SESSION_KEY, $list);
			$this->setInfo($id);
			return $list;
		}
		return false;
	}

	public function persistentExists(string $id):bool{
		return CacheManager::$cache->exists(self::CACHE_KEY . $id);
	}

	public function persistentSave($user):string{

		$this->getInfo();
		$id = $this->getUniqid($user);
		$this->setInfo($id);
		try {
			CacheManager::$cache->store(self::CACHE_KEY .$id, $this->getList());
			$this->message['success']="La liste a été sauvegardée sous l'id <b>$id</b>.<br>Elle sera accessible depuis l'url " . $this->getButtonUrl(Router::url('todos.loadList', [$id]));
		}catch(CacheException $e){
			$this->message['error']=$e->getMessage();
		}
		return $id;
	}

	public function getTitle():string{
		$this->getInfo();
		$title=$this->info['id'];
		if($this->info['updated']){
			$title.=' [*]';
		}
		return $title;
	}

	public function addElements(string|array $elms):array{
		$list = $this->getList();
		if (\is_array($elms)) {
			foreach ($elms as $e) {
				if($e!='') {
					$list[] = $e;
				}
			}
		}elseif ($elms){
			$list[] = $elms;
		}
		$this->setList($list);
		return $list;
	}

	public function deleteElement(int $index):array{
		$list = $this->getList();
		if (isset($list[$index])) {
			$toDelete=$list[$index];
			unset($list[$index]);
			$list=\array_values($list);
			$this->setList($list);
			$this->message['success']="L'élément <b>$toDelete</b> a été supprimé.";
			return $list;
		}
		$this->message['error']="L'élément d'index <b>$index</b> n'existe pas.";
		return $list;
	}

	public function updateElement(int $index,string $value):array{
		$list = $this->getList();
		if ($value && isset($list[$index])) {
				$list[$index] = $value;
				$this->setList($list);
			$this->message['success']="L'élément à l'index <b>$index</b> a été modifié.";
			return $list;
		}
		$this->message['error']="L'élément d'index <b>$index</b> n'existe pas.";
		return $list;
	}

	public function getMessage(string $type):string {
		return $this->message[$type]??'';
	}

	public function hasError():string {
		return isset($this->message['error']);
	}
}
