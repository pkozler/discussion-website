
<?php

namespace App\Presenters;

use Nette,
	App\Model,
	Nette\Forms\Form,
	Nette\Forms\Controls;

use Tracy\Debugger;

/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
	const
		MAX_TEXT_LENGTH = 20000,
		MAX_NAME_LENGTH = 80,
		MAX_PASSWORD_LENGTH = 8,
		MIN_AGE = 1,
		MAX_AGE = 100,
		NICKNAME_PATTERN = '[a-žA-Ž0-9 _-]+',
		RESULTS_PER_PAGE = 100
	;
	
	/**
	 * Přidá IP adresu v platném IPv6 formátu převedenou do binární podoby
	 * 
	 * @param unknown $values hodnoty formuláře
	 */
	protected function addIpAddress($values) {
		$httpRequest = $this->context->getByType('Nette\Http\Request');
		$ip = $httpRequest->getRemoteAddress();
	
		$values->ip = inet_pton($ip);
	}
	
	/**
	 * Sjednotí hodnoty - "prázdné" hodnoty se v databázi uloží jako NULL
	 * 
	 * @param unknown $values hodnoty formuláře
	 */
	protected function emptyToNull($values) {
		foreach ($values as $key => $value)
		{
			if ($key === 'email' && ($values->$key === '@' || $values->$key === '')
					|| is_string($values->$key) && $values->$key === ''
					|| is_int($values->$key) && $values->$key == 0) {
						$values->$key = NULL;
					}
		}
	}
	
	/**
	 * Bootstap 3 Nette Forms rendering.
	 */
	protected function bootstrapFormRender($form) {
		// setup form rendering
		$renderer = $form->getRenderer();
		$renderer->wrappers['controls']['container'] = NULL;
		$renderer->wrappers['pair']['container'] = 'div class=form-group';
		$renderer->wrappers['pair']['.error'] = 'has-error';
		$renderer->wrappers['control']['container'] = 'div class=col-sm-9';
		$renderer->wrappers['label']['container'] = 'div class="col-sm-3 control-label"';
		$renderer->wrappers['control']['description'] = 'span class=help-block';
		$renderer->wrappers['control']['errorcontainer'] = 'span class=help-block';
		// make form and controls compatible with Twitter Bootstrap
		$form->getElementPrototype()->class('form-horizontal');
		
		foreach ($form->getControls() as $control) {
			if ($control instanceof Controls\Button) {
				$control->getControlPrototype()->addClass(empty($usedPrimary) ? 'btn btn-primary' : 'btn btn-default');
				$usedPrimary = TRUE;
			}
			elseif ($control instanceof Controls\TextBase
					|| $control instanceof Controls\SelectBox
					|| $control instanceof Controls\MultiSelectBox) {
				$control->getControlPrototype()->addClass('form-control');
			}
			elseif ($control instanceof Controls\Checkbox
					|| $control instanceof Controls\CheckboxList
					|| $control instanceof Controls\RadioList) {
				$control->getSeparatorPrototype()->setName('div')->addClass($control->getControlPrototype()->type);
			}
		}
		
		return $form;
	}
	
}
