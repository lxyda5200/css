<?php
namespace app\common\tool;


/**
 * Class Form
 * @package app\common\tool
 * @method Form select($column, $label = '')    select框
 * @method Form text($column, $label = '')      input框
 * @method Form radio($column, $label = '')     单选框
 * @method Form checkbox($column, $label = '')  复选框
 * @method Form button($column, $label = '')    按钮
 * @method Form hidden($column, $label = '')    隐藏域
 * @method Form textarea($column, $label = '')  文本域
 * @method Form image($column, $label = '')     图片
 * @method Form rate($column, $label = '')      评分
 */
class Form
{
    protected $name;
    protected $fields = [];
    protected $formId;


    /**
     * 设置表单id
     * @param $formId
     * @return $this
     */
    public function formId($formId) {
        $this->formId = $formId;
        return $this;
    }

    /**
     * 基本组件
     * @return string
     */
    public function baseField() {
        return
        '<div class="layui-form-item">
            <label class="layui-form-label">%s</label>
            <div class="layui-input-block">
                %s
            </div>
        </div>';
    }


    /**
     * 构建input框placeholder
     * @param $placeholder
     * @return $this
     */
    public function placeholder($placeholder) {
        $this->fields[$this->name] = array_merge($this->fields[$this->name], [
            'placeholder' => $placeholder
        ]);
        return $this;
    }


    /**
     * 构建select框
     * @param $field
     * @return string
     */
    private function selectField($field) {
        $select = sprintf('<select name="%s">', $field['name']);

        foreach ($field['options'] as $option) {
            $select .= sprintf('<option value="%s">%s</option>', $option['value'], $option['title']);
        }
        return $select . '</select>';
    }


    /**
     * 构建input框
     * @param $field
     * @return string
     */
    private function textField($field) {
        return sprintf('<input name="%s" placeholder="%s" class="layui-input" type="text" value="">',
            $field['name'], $field['placeholder']);
    }


    /**
     * 构建textarea文本域
     * @param $field
     * @return string
     */
    private function textareaField($field) {
        return sprintf('<textarea name="%s" placeholder="%s" class="layui-textarea">%s</textarea>',
            $field['name'],
            $field['placeholder'],
            isset($field['default'])?$field['default']:""
        );
    }


    /**
     * 构建radio单选
     * @param $field
     * @return string
     */
    private function radioField($field) {
        $radio_ = '';

        foreach ($field['radios'] as $radio) {
            $radio_ .= sprintf('<input type="radio" name="%s" value="%s" title="%s" %s>',
                $field['name'],
                $radio['value'],
                $radio['title'],
                $field['default'] == $radio['value'] ? "checked" : ""
            );
        }
        return $radio_;
    }


    /**
     * 构建checkbox复选
     * @param $field
     * @return string
     */
    private function checkboxField($field) {
        $checkbox_ = '';

        foreach ($field['checkboxes'] as $checkbox) {
            $checkbox_ .= sprintf('<input type="checkbox" name="%s" title="%s" value="%s" lay-skin="primary" %s>',
                $field['name'],
                $checkbox['title'],
                $checkbox['value'],
                isset($field['defaults'])?(in_array($checkbox['value'], $field['defaults'])?"checked":""):""
            );
        }
        return $checkbox_;
    }


    /**
     * 构建button
     * @param $field
     * @return string
     */
    private function buttonField($field) {
        $button_ = '';

        foreach ($field['buttons'] as $button) {
            $button_ .= sprintf('<button class="layui-btn" type="button" id="%s" lay-filter="%s">%s</button>',
                $button['id'],
                $button['filter'],
                $button['value']
            );
        }
        return $button_;
    }


    /**
     * 构建hidden隐藏域
     * @param $field
     * @return string
     */
    private function hiddenField($field) {
        return sprintf('<input id="%s" type="hidden" value="%s" />',
            $field['id'],
            $field['default']
        );
    }


    /**
     * 构建图片上传
     * @param $field
     * @return string
     */
    private function imageField($field) {
        return sprintf('<div class="layui-upload-drag" id="%s" style="float:left;">
                                    <i class="layui-icon"></i>
                                    <p>点击上传，或将文件拖拽到此处</p>
                                </div>
                                <div style="float:left;margin-left:10px;position:relative;display:%s;">
                                    <input name="%s" type="hidden" value="%s" />
                                    <img class="img-cover" style="max-width:300px;max-height:135px;" src="%s" />
                                    <i style="cursor:pointer;position:absolute;right:0;top:0;padding:3px;" class="layui-icon del-cover">&#xe640;</i>
                                </div>',
            $field['name'],
            isset($field['default'])?"block":"none",
            $field['name'],
            isset($field['default'])?($field['default']):"",
            isset($field['default'])?($field['default']):""
        );
    }


    /**
     * 构建投票组件
     * @param $field
     * @return string
     */
    public function rateField($field) {
        return sprintf('<div id="%s"></div>', $field['id']);
    }


    /**
     * 设置id
     * @param $id
     * @return $this
     */
    public function setId($id) {
        $this->fields[$this->name] = array_merge($this->fields[$this->name], [
            'id' => $id
        ]);
        return $this;
    }


    /**
     * 设置默认值
     * @param $value
     * @return $this
     */
    public function setDefault($value) {
        $this->fields[$this->name] = array_merge($this->fields[$this->name], [
            'default' => $value
        ]);
        return $this;
    }


    /**
     * 设置多个默认值   [1,2,3]
     * @param $val_arr
     * @return $this
     */
    public function setDefaults($val_arr) {
        $this->fields[$this->name] = array_merge($this->fields[$this->name], [
            'defaults' => $val_arr
        ]);
        return $this;
    }


    /**
     * 构建select框中的option  [['title' => '', 'value' => '']]
     * @param $options
     * @return $this
     */
    public function options($options) {
        $this->fields[$this->name] = array_merge($this->fields[$this->name], [
            'options' => $options
        ]);
        return $this;
    }


    /**
     * 构建radio框中的数据 [['title' => '', 'value' => '']]
     * @param $radios
     * @return $this
     */
    public function radios($radios) {
        $this->fields[$this->name] = array_merge($this->fields[$this->name], [
            'radios' => $radios
        ]);
        return $this;
    }


    /**
     * 构建checkbox框中的数据  [['title' => '', 'value' => '']]
     * @param $checkboxes
     * @return $this
     */
    public function checkboxes($checkboxes) {
        $this->fields[$this->name] = array_merge($this->fields[$this->name], [
            'checkboxes' => $checkboxes
        ]);
        return $this;
    }


    /**
     * 构建button数据  [['id' => '', 'value' => '', 'filter' => '']]
     * @param $buttons
     * @return $this
     */
    public function buttons($buttons) {
        $this->fields[$this->name] = array_merge($this->fields[$this->name], [
            'buttons' => $buttons
        ]);
        return $this;
    }


    /**
     * 获取构建的表单
     * @return string
     */
    public function render() {
        $form = '<div class="tplay-body-div"><div style="margin-top: 20px;"></div>';
        $form .= sprintf('<form class="layui-form" id="%s" lay-filter="%s">', $this->formId, $this->formId);

        foreach ($this->fields as $field) {
            $form .= in_array($field['type'], ['hidden']) ?
                $this->{$field['type'].'Field'}($field) :
                sprintf($this->baseField(),
                    isset($field['label'])?$field['label']:'',
                    $this->{$field['type'].'Field'}($field));
        }

        return $form.'</form></div>';
    }


    public function __call($method, $arguments)
    {
        $this->name = isset($arguments[0])?$arguments[0]:'';
        $label = isset($arguments[1])?$arguments[1]:'';

        $this->fields[$this->name] = [
            'name' => $this->name,
            'type' => $method,
            'label' => $label
        ];

        return $this;
    }
}