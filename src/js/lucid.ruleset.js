lucid.ruleset = {
    'form':{},
    'handlers':{},
    'mode':'single-list',
    'formAlert':'<div class="alert alert-danger clearfix" id=":id" style="display:none;"><strong>Form Errors</strong><p>test</p></div>'
};

/* START COMPAT FUNCTIONS: These functions allows the exact same php code to run as javascript */
function strlen(str){
    return strval(str).length;
}

function strval(str){
    return String(str);
}
/* END COMPAT FUNCTIONS */

lucid.ruleset.add=function(name,rules){
    lucid.ruleset.form[name] = rules;
};

lucid.ruleset.process=function(name, data){
    if(typeof(lucid.ruleset.form[name]) == 'object'){
        var errorList = {};
        var errorCount = 0;
        var rules = lucid.ruleset.form[name];
        for(var i=0; i < rules.length; i++){
            if(typeof(lucid.ruleset.handlers[rules[i].type]) == 'function'){
                if( lucid.ruleset.handlers[rules[i].type](rules[i],data) === false){
                    if(typeof(errorList[rules[i]['label']]) != 'object'){
                        errorList[rules[i]['label']] = [];
                    }
                    errorList[rules[i]['label']].push(rules[i].message);
                    errorCount++;
                }
            }else{
                console.log('Unknown validation rule: ' + rules[i].type);
            }
        }
        if(errorCount > 0){
            lucid.ruleset.showErrors(name, errorList);
            return false;
        }
        return true;
    }else{
        console.log('no rules found');
        return true;
    }
}

lucid.ruleset.showErrors=function(name,errors){
    var form = document.forms[name];
    var data = lucid.getFormValues(form);
    var formErrorId = window.jQuery(form).attr('name')+'-errors';
    if (jQuery('#'+formErrorId).length ==0){
        jQuery(form).prepend(lucid.ruleset.formAlert.replace(':id',formErrorId));
    }
    jQuery('#'+formErrorId +' > p').html(lucid.ruleset.buildErrorHtml(errors));
    jQuery('#'+formErrorId).fadeIn(300);
}

lucid.ruleset.clearErrors=function(form){
    var formErrorId = jQuery(form).attr('name')+'-errors';
    jQuery('#'+formErrorId).hide();
}

lucid.ruleset.buildErrorHtml =function(errors){
    if(lucid.ruleset.mode == 'single-categorized'){
        var html = '<dl class="dl-horizontal">';
        for(var field in errors){
            html += '<dt class="col-sm-3">' + field + '</dt>';
            for(var i=0; i<errors[field].length; i++){
                html += '<dd class="col-sm-9' +((i == 0)?'':' col-sm-offset-3')+ '">';
                html += errors[field][i] + '</dd>';
            }
        }
        html += '</dl>';
    }
    if(lucid.ruleset.mode == 'single-list')
    {
        var html = '<ul class="list-unstyled">';
        for(var field in errors){
            for(var i=0; i<errors[field].length; i++){
                html += '<li>'+errors[field][i]+'</li>';
            }
        }
        html += '</ul>';
    }

    return html;
}

lucid.ruleset.handlers.lengthRange = function($rule, $data){
    $rule.last_value = strval($data[$rule.field]);
    return (strlen($rule['last_value']) >= $rule['min'] && strlen($rule['last_value']) < $rule['max']);
};

lucid.ruleset.handlers.integerValue = function($rule, $data){
    $rule.last_value = parseInt($data[$rule.field]);
    if(isNaN($rule.last_value)){
        return false;
    }
    return true;
};


lucid.ruleset.handlers.integerValueMin = function($rule, $data){
    $rule.last_value = parseInt($data[$rule.field]);

    if(isNaN($rule.last_value)){
        return false;
    }
    if('min' in $rule && $rule.last_value < $rule.min){
        return false;
    }
    return true;
};

lucid.ruleset.handlers.integerValueMax = function($rule, $data){
    $rule.last_value = parseInt($data[$rule.field]);

    if(isNaN($rule.last_value)){
        return false;
    }
    if('max' in $rule && $rule.last_value > $rule.max){
        return false;
    }

    return true;
};

lucid.ruleset.handlers.integerValueMinMax = function($rule, $data){
    $rule.last_value = parseInt($data[$rule.field]);

    if(isNaN($rule.last_value)){
        return false;
    }
    if('min' in $rule && $rule.last_value < $rule.min){
        return false;
    }
    if('max' in $rule && $rule.last_value > $rule.max){
        return false;
    }
    return true;
};

lucid.ruleset.handlers.checked = function($rule, $data){
    $rule.last_value = $data[$rule.field];
    return ($rule.last_value === true);
};

lucid.ruleset.handlers.anyValue = function($rule, $data){
    $rule.last_value = strval($data[$rule.field]);
    return ($rule.last_value !== '' && $rule.last_value+'' != 'undefined');
};

lucid.ruleset.handlers.floatValue = function($rule, $data){
    $rule.last_value = parseInt($data[$rule.field]);
    if(isNaN($rule.last_value)){
        return false;
    }
    return true;
};

lucid.ruleset.handlers.validDate = function($rule, $data){
    console.log('validDate rule not implemented yet :(');
    return true;
};
