<?php
namespace services;

 use Ajax\php\ubiquity\UIService;
 use Ajax\semantic\components\validation\Rule;
 use Ajax\semantic\html\base\constants\TextAlignment;
 use Ajax\semantic\html\collections\HtmlMessage;
 use Ajax\semantic\html\elements\HtmlLabel;
 use Ajax\semantic\html\elements\HtmlList;
 use Ubiquity\cache\CacheFile;
 use Ubiquity\cache\CacheManager;
 use Ubiquity\controllers\Router;

 /**
  * Class UITodosService
  */
class UITodosService extends UIService {
	public function displayList(array $list,string $title) {
		if(\count($list)>0){
			$this->jquery->show('._saveList','','',true);
		}
		$js = 'let $item=$(event.target).closest("div.item");$item.children(".ui.checkbox").toggle();$item.children("form").toggle();';
		$this->jquery->click('._toEdit', $js,true,true);
		$this->jquery->change('#multiple','$("._form").toggle();',false,false,false);
		$this->jquery->dblclick('._element', $js,true,true);
		$this->jquery->renderView('TodosController/displayList.html', compact('list','title'));
	}

	public function createAccount(){
		$frm=$this->semantic->dataForm('frm',['title'=>'Création de Nouveau compte','email'=>'','password'=>'','password-conf'=>'','submit'=>'']);
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
	}

	public function myLists(array $files,string $ku){
		$dt=$this->semantic->dataTable('dt',CacheFile::class,$files);
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
	}
}
