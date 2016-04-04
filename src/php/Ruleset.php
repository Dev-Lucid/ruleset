<?php
namespace Lucid\Component\Ruleset;
use Lucid\Lucid;

abstract class Ruleset implements \Lucid\Component\Factory\FactoryObjectInterface
{
    use \Lucid\Component\Factory\FactoryObjectTrait;

    public $rules = [];
    public $name  = '';
    public $originalFile = '';
    public $originalLine = null;

    public static $handlers = [];

    public function __construct ()
    {
        $caller =  debug_backtrace()[0];
        $this->originalFile = $caller['file'];
        $this->originalLine = $caller['line'];
    }

    public function addRule($rule)
    {
        $rule['ruleset'] = $this;
        $this->rules[] = $rule;
        return $this;
    }

    public static function checkForKeys(array $rule, ...$requiredKeys) {
        # always check for key 'field'
        $requiredKeys[] = 'field';

        foreach ($requiredKeys as $key) {
            if (isset($rule[$key]) === false) {
                throw new \Exception('The ruleset defined on line '.$rule['ruleset']->originalLine.' in file '.$rule['ruleset']->originalFile.' contains a rule that is missing a required property. Rules with type '.$rule['type'].' must have these keys: '.implode(', ', $requiredKeys).'.');
            }
        }
        return true;
    }

    public function send ($formName = 'edit')
    {
        # before sending rules, ensure as best we can that the ruleset is actually valid.
        foreach ($this->rules as $rule) {

            # first, check to make sure the rule actually has a 'type', which is what determines which function is called to check the data
            if (isset($rule['type']) === false) {
                $backtrace = debug_backtrace()[0];
                throw new \Exception('The ruleset defined on line '.$this->originalLine.' in file '.$this->originalFile.' contains a rule without a type. Every rule must have a type. Valid types are: '.implode(', ', array_keys(Ruleset::$handlers)).'.');
            }

            # then, ensure that the handler for that rule actually exists
            $func = static::$handlers[$rule['type']];
            if (is_callable($func) === false) {
                throw new \Exception('The ruleset defined on line '.$this->originalLine.' in file '.$this->originalFile.' contains a rule with an invalid type. Valid types are: '.implode(', ', array_keys(Ruleset::$handlers)).'.');
            }

            # finally, call the rule with $data set to null. All rules should check for this condition, and use it to ensure that the rule contains
            # all necessary array indices to perform their function
            $result = $func($rule, null);
        }

        # add validation messages, and unset the ruleset index since that can't be sent via json anyway
        foreach ($this->rules as $key=>$value) {
            unset($this->rules[$key]['ruleset']);
            if (isset($this->rules[$key]['message']) === false) {
                $this->rules[$key]['message'] = lucid::i18n()->translate('validation:'.$this->rules[$key]['type'], $this->rules[$key]);
            }
        }
        $js = 'lucid.ruleset.add(\''.$formName.'\', '.json_encode($this->rules).');';
        lucid::response()->javascript($js);
    }

    public function hasErrors ($dataToCheck = null)
    {
        if (is_null($dataToCheck)) {
            $data = lucid::request();
        } elseif (is_array($dataToCheck) === true) {
            $data = new \Lucid\Component\Store\Store($dataToCheck);
        }

        $errors = [];
        foreach ($this->rules as $rule) {
            if (isset(static::$handlers[$rule['type']]) === true && is_callable(static::$handlers[$rule['type']]) === true) {
                $func = static::$handlers[$rule['type']];
                $result = $func($rule, $data);
                if ($result === false) {
                    if (isset($errors[$rule['label']]) === false) {
                        $errors[$rule['label']] = [];
                    }

                    if (isset($rule['message']) === true) {
                        $message = \Lucid\lucid::i18n()->translate($rule['message'], $rule);
                    } else {
                        $message = \Lucid\lucid::i18n()->translate('validation:'.$rule['type'],$rule);
                    }
                    $errors[$rule['label']][] = $message;
                }
            }
        }

        if (count($errors) > 0) {
            lucid::logger()->debug('Validation failure: ');
            lucid::logger()->debug(print_r($errors, true));
            return $errors;
        }
        return false;
    }

    public function sendErrors($data = null)
    {
        if (($errors = $this->hasErrors($data)) == false){
            lucid::logger()->debug('no errors found');
            return;
        }
        lucid::response()->javascript('lucid.ruleset.showErrors(\''.lucid::request()->string('__form_name').'\','.json_encode($errors).');');
        lucid::response()->send('validationError');
    }

    public function checkParameters(array $passedParameters)
    {
        # This function determines what the names of the parameters sent to the function calling this should have been
        # named, then rebuilds the numerically indexed array of parameters into an associative array,
        # and then calls sendErrors.
        $caller =  debug_backtrace()[1];
        $r = new \ReflectionMethod($caller['class'], $caller['function']);
        $functionParameters = $r->getParameters();

        $finalParameters = [];
        for ($i=0; $i<count($functionParameters); $i++) {
            $finalParameters[$functionParameters[$i]->name] = $passedParameters[$i] ?? null;
        }

        return $this->sendErrors($finalParameters);
    }

    public static function sendError(string $field, $msg = null)
    {
        if (is_null($msg) === true) {
            $msg = $field;
            $field = '';
        }
        $errors = [$field=>[$msg]];
        lucid::response()->javascript('lucid.ruleset.showErrors(\''.lucid::request()->string('__form_name').'\', '.json_encode($errors).');');
        lucid::response()->send();
    }
}

Ruleset::$handlers['lengthRange'] = function (array $rule, $data) {
    # Before rules are sent, they are called with null passed in $data. This is used so that you can check if the rule array contains
    # all the keys that it needs to do its job. For example, this rule may require a min and max index be set in the rule array. Even if your
    # rule does not require any keys, call this function anyway as it will always check for the key 'field', and so that you don't forget
    # to call the function if you choose to modify your code later to add a required key.
    if (is_null($data) === true) {
        return Ruleset::checkForKeys($rule, 'min', 'max');
    }
    $rule['last_value'] = $data->string($rule['field']);
    return (strlen($rule['last_value']) >= $rule['min'] && strlen($rule['last_value']) < $rule['max']);
};


Ruleset::$handlers['integerValue'] = function($rule, $data){
    # Before rules are sent, they are called with null passed in $data. This is used so that you can check if the rule array contains
    # all the keys that it needs to do its job. For example, this rule may require a min and max index be set in the rule array. Even if your
    # rule does not require any keys, call this function anyway as it will always check for the key 'field', and so that you don't forget
    # to call the function if you choose to modify your code later to add a required key.
    if (is_null($data) === true) {
        return Ruleset::checkForKeys($rule);
    }
    $rule['last_value'] =  $data->int($rule['field']);
    if (is_numeric($rule['last_value']) === true && intval($rule['last_value']) == $rule['last_value']){
        return false;
    }
    return true;
};


Ruleset::$handlers['integerValueMin'] = function($rule, $data){
    # Before rules are sent, they are called with null passed in $data. This is used so that you can check if the rule array contains
    # all the keys that it needs to do its job. For example, this rule may require a min and max index be set in the rule array. Even if your
    # rule does not require any keys, call this function anyway as it will always check for the key 'field', and so that you don't forget
    # to call the function if you choose to modify your code later to add a required key.
    if (is_null($data) === true) {
        return Ruleset::checkForKeys($rule, 'min');
    }
    $rule['last_value'] = $data->int($rule['field']);
    if (is_numeric($rule['last_value']) === true && intval($rule['last_value']) == $rule['last_value']){
        return false;
    }
    if (intval($rule['last_value']) < $rule['min']) {
        return false;
    }
    return true;
};

Ruleset::$handlers['integerValueMax'] = function($rule, $data){
    # Before rules are sent, they are called with null passed in $data. This is used so that you can check if the rule array contains
    # all the keys that it needs to do its job. For example, this rule may require a min and max index be set in the rule array. Even if your
    # rule does not require any keys, call this function anyway as it will always check for the key 'field', and so that you don't forget
    # to call the function if you choose to modify your code later to add a required key.
    if (is_null($data) === true) {
        return Ruleset::checkForKeys($rule, 'max');
    }
    $rule['last_value'] = $data->int($rule['field']);
    if (is_numeric($rule['last_value']) === true && intval($rule['last_value']) == $rule['last_value']){
        return false;
    }
    if (intval($rule['last_value']) > $rule['max']) {
        return false;
    }
    return true;
};

Ruleset::$handlers['integerValueMinMax'] = function($rule, $data){
    # Before rules are sent, they are called with null passed in $data. This is used so that you can check if the rule array contains
    # all the keys that it needs to do its job. For example, this rule may require a min and max index be set in the rule array. Even if your
    # rule does not require any keys, call this function anyway as it will always check for the key 'field', and so that you don't forget
    # to call the function if you choose to modify your code later to add a required key.
    if (is_null($data) === true) {
        return Ruleset::checkForKeys($rule, 'min', 'max');
    }
    $rule['last_value'] = $data->int($rule['field']);
    if (is_numeric($rule['last_value']) === true && intval($rule['last_value']) == $rule['last_value']){
        return false;
    }
    if (intval($rule['last_value']) < $rule['min']) {
        return false;
    }
    if (intval($rule['last_value']) > $rule['max']) {
        return false;
    }
    return true;
};

Ruleset::$handlers['checked'] = function($rule, $data){
    # Before rules are sent, they are called with null passed in $data. This is used so that you can check if the rule array contains
    # all the keys that it needs to do its job. For example, this rule may require a min and max index be set in the rule array. Even if your
    # rule does not require any keys, call this function anyway as it will always check for the key 'field', and so that you don't forget
    # to call the function if you choose to modify your code later to add a required key.
    if (is_null($data) === true) {
        return Ruleset::checkForKeys($rule);
    }
    return ($data->string($rule['field']) == '1');
};

Ruleset::$handlers['anyValue'] = function($rule, $data){
    # Before rules are sent, they are called with null passed in $data. This is used so that you can check if the rule array contains
    # all the keys that it needs to do its job. For example, this rule may require a min and max index be set in the rule array. Even if your
    # rule does not require any keys, call this function anyway as it will always check for the key 'field', and so that you don't forget
    # to call the function if you choose to modify your code later to add a required key.
    if (is_null($data) === true) {
        return Ruleset::checkForKeys($rule);
    }
    $rule['last_value'] = $data->string($rule['field'], null);
    return ($rule['last_value'] !== '' && is_null($rule['last_value']) === false);
};

Ruleset::$handlers['floatValue'] = function($rule, $data){
    # Before rules are sent, they are called with null passed in $data. This is used so that you can check if the rule array contains
    # all the keys that it needs to do its job. For example, this rule may require a min and max index be set in the rule array. Even if your
    # rule does not require any keys, call this function anyway as it will always check for the key 'field', and so that you don't forget
    # to call the function if you choose to modify your code later to add a required key.
    if (is_null($data) === true) {
        return Ruleset::checkForKeys($rule);
    }
    $rule['last_value'] =$data->string($rule['field']);
    if (is_numeric($rule['last_value']) === true && floatval($rule['last_value']) == $rule['last_value']) {
        return false;
    }
    return true;
};

Ruleset::$handlers['validDate'] = function($rule, $data){
    # Before rules are sent, they are called with null passed in $data. This is used so that you can check if the rule array contains
    # all the keys that it needs to do its job. For example, this rule may require a min and max index be set in the rule array. Even if your
    # rule does not require any keys, call this function anyway as it will always check for the key 'field', and so that you don't forget
    # to call the function if you choose to modify your code later to add a required key.
    if (is_null($data) === true) {
        return Ruleset::checkForKeys($rule);
    }
    lucid::logger()->debug('validDate rule not implemented yet :(');
    return true;
};
