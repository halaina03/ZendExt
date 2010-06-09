<?php
/**
 * CRUD Controller.
 *
 * @category  ZendExt
 * @package   ZendExt_Controller
 * @copyright 2010 Monits
 * @license   Copyright (C) 2010. All rights reserved.
 * @version   Release: 1.0.0
 * @link      http://www.monits.com/
 * @since     1.0.0
 */

/**
 * CRUD Controller.
 *
 * @category  ZendExt
 * @package   ZendExt_Controller
 * @author    itirabasso <itirabasso@monits.com>, lbritez <lbritez@monits.com>
 * @copyright 2010 Monits
 * @license   Copyright 2010. All rights reserved.
 * @version   Release: 1.0.0
 * @link      http://www.monits.com/
 * @since     1.0.0
 */
abstract class ZendExt_Controller_CRUDAbstract
    	extends Zend_Controller_Action
{
    protected $_builderClass = null;
    protected $_fieldToColumnMap = null;
    protected $_itemsPerPage = 10;

    protected $_dataSource = null;

    protected $_formName = '';

    const DEFAULT_PAGE = 1;

    /**
     * indexAction.
     *
     * @return void
     */
    public function indexAction()
    {
        $this->_forward('list');
    }

    /**
     * listAction.
     *
     * @return void
     */
    public function listAction()
    {
        $request = $this->getRequest();

        $pk = $this->_dataSource->getPk();
        $builder = new $this->_builderClass();
        $fields = $builder->getFieldsNames();

        $page           = $this->_getParam('page', self::DEFAULT_PAGE);
        $ipp            = $this->_getParam('ipp', $this->_itemsPerPage);
        $orderBy        = $this->_getParam('by', $pk);
        $orderAlignment = $this->_getParam('order', 'ASC');

        if ($orderBy != $pk) {
            $orderBy = $this->_fieldToColumnMap[$orderBy];
        }

        if (is_array($orderBy)) {
            $orderBy = implode(',', $pk);
        }

        $table = $this->_dataSource->getTable();

        $select = $table->select()
                        ->order(
                            $orderBy . ' '
                            . ($orderAlignment == 'ASC' ? 'ASC' : 'DESC')
                        );

        $paginator = Zend_Paginator::factory($select);
        $paginator->setCurrentPageNumber($page);
        $paginator->setItemCountPerPage($ipp);

        $this->view->paginator = $paginator;
        $this->view->pk = $pk;
        $this->view->fieldsMap = $this->_fieldToColumnMap;

        $renderer = new ZendExt_Crud_Template_List($this->view);
        $renderer->render('List of ' . $this->_builderClass);

    }

    /**
     * newAction.
     *
     * @return void
     */
    public function newAction()
    {
        $request = $this->getRequest();

        if (!$request->isPost()) {
            // Assign the form
            $this->view->form = $this->_newForm();

            // Render the script
            $renderer = new ZendExt_Crud_Template_New($this->view);

            $renderer->render($this->_formName);
            $this->_helper->viewRenderer->setNoRender();
            return;
        }

        $data = array();
        $builder = new $this->_builderClass();
        $fields = $builder->getFieldsNames();

        /*
         * If it's a sequence, the pk should be autogenerated,
         * remove them from field list.
         */
        if ($this->_dataSource->isSequence()) {
            $pk = $this->_dataSource->getPk();
            $this->_unsetPK($pk, $fields);
        }

        try {
            $table = $this->_dataSource->getTable();

            $data = $this->_completeData($fields, $table);

            $table->insert($data);
            $this->_redirect('/' . $request->getControllerName() . '/list');

        } catch (ZendExt_Builder_ValidationException $e) {
            $this->view->failedField = $e->getField();
            $this->view->errors = $e->getErrors();
        }
    }

    /**
     * updateAction.
     *
     * @return void
     */
    public function updateAction()
    {
        $request = $this->getRequest();

        if (!$request->isPost()) {

            // Retrieve params for primary key
            $pkFields = $this->_dataSource->getPk();

            $pk = array();
            foreach ($pkFields as $column) {
                $fieldName = array_search($column, $this->_fieldToColumnMap);
                $pk[$fieldName] = $request->getParam($fieldName);
            }

            // Display the form with the current values
            $this->view->Updateform = $this->_newForm($pk);
            // Render the script
            $renderer = new ZendExt_Crud_Template_Update($this->view);

            $renderer->render($this->_formName);
            $this->_helper->viewRenderer->setNoRender();
            return;
        }

        // Update database!
        $builder = new $this->_builderClass();
        $fields = $builder->getFieldsNames();

        $pk = $this->_dataSource->getPk();

        $this->_unsetPK($pk, $fields);

        $data = array();
        try{
            $table = $this->_dataSource->getTable();

            $data = $this->_completeData($fields, $table);

            $where = $this->_completeWhere($pk, $table);

            $table->update($data, $where);

            $this->_redirect('/' . $request->getControllerName() . '/list');
        } catch (ZendExt_Builder_ValidationException $e) {
            $this->view->failedField = $e->getField();
            $this->view->errors = $e->getErrors();
        }


    }

    /**
     * deleteAction.
     *
     * @return void
     */
    public function deleteAction()
    {
        $request = $this->getRequest();

        if (!$request->isPost()) {
            $this->_redirect('/' . $request->getControllerName() . '/list');
            return;
        }

        $pk = $this->_dataSource->getPk();

        try {
            $table = $this->_dataSource->getTable();

            $where = $this->_completeWhere($pk, $table);

            $table->delete($where);

        } catch (ZendExt_Builder_ValidationException $e) {
            $this->view->failedField = $e->getField();
            $this->view->errors = $e->getErrors();
        }

        $this->_redirect('/' . $request->getControllerName() . '/list');
    }

    /**
     * Retrieves the WHERE sentence for each primary key.
     *
     * @param string|array           $pk    The primary key of the table.
     * @param Zend_Db_Table_Abstract $table The table.
     *
     * @return array
     */
    private function _completeWhere($pk, $table)
    {
        $adapter = $table->getAdapter();
        $builder = new $this->_builderClass();
        $where = array();

        foreach ($pk as $k) {
            $field = array_search($k, $this->_fieldToColumnMap);
            $method = 'with' . ucfirst($field);
            $value = $this->_getParam($field);

            $builder->$method($value);
            $where[] = $adapter->quoteInto($k . ' = ?', $value);
        }

        return $where;
    }

    /**
     * Retrieves the data from the form for each field.
     *
     * @param array                  $fields The names of the fields.
     * @param Zend_Db_Table_Abstract $table  The table.
     *
     * @return array
     */
    private function _completeData(array $fields, $table)
    {
        $adapter = $table->getAdapter();
        $builder = new $this->_builderClass();
        $data = array();

        foreach ($fields as $field) {
            $method = 'with' . ucfirst($field);
            $value = $this->_getParam($field);

            // If empty, take the default (if there is any)
            if (empty($value) && $builder->hasDefault($field)) {
                $value = $builder->getDefault($field);
            }

            // Only validate if the given value is not a default
            if (!$builder->hasDefault($field) || $value != $builder->getDefault($field)) {
                $builder->$method($value);
            }

            $data[$this->_fieldToColumnMap[$field]] = $value;
        }

        return $data;
    }

    /**
     * Unset the primary key in the form.
     *
     * @param array|string $pk     The primary key to be unseted.
     * @param array        $fields The name of the felds.
     *
     * @return void
     */
    private function _unsetPK($pk, $fields)
    {
        foreach ((array) $pk as $k) {
            $pkField = array_search($k, $this->_fieldToColumnMap);
            $indexField = array_search($pkField, $fields);
            unset($fields[$indexField]);
        }
    }

    /**
     * Retrieves the row for the primary key.
     *
     * @param array $pk Array for field => value of primary to do the lookup.
     *
     * @return array
     */
    private function _getRow(array $pk)
    {

        $table = $this->_dataSource->getTable();
        $select = $table->select();

        foreach ($pk as $field => $value) {
            $column = $this->_fieldToColumnMap[$field];
            $select->where($column . ' = ?', $value);
        }

        $row = $table->fetchRow($select);
        if (null === $row) {
            return null;
        }

        return $row->toArray();
    }

    /**
     * Create a new form.
     *
     * @param array $pk Optional array for field => value of primary to do the lookup.
     *
     * @return void.
     */
    private function _newForm(array $pk = null)
    {
        $row = null;

        if (null !== $pk) {
            $row = $this->_getRow($pk);
        }
        $builder = new $this->_builderClass();
        $fields = $builder->getFieldsNames();

        $form = new Zend_Form();
        $form->setAttrib('id', '')
             ->setAttrib('class', '')
             ->setAction('')
             ->setMethod('post')
             ->addDecorator('HtmlTag', array('tag' => 'dl','class' => ''));

        foreach ($fields as $field) {
            $type = $this->_getType($field);

            $options = array(
                        'Label'    => $field . ':',
                        //TODO: Verificar en la db o builder si el campo es requerido o no
                        'required' => true
                       );

            if ($type == 'hidden') {
                $options = null;
            }

            $form->addElement($type , $field, $options);

            if (null !== $row) {
                $column = $this->_fieldToColumnMap[$field];
                $value = $row[$column];
                $form->setDefault($field, $value);
            }
        }
        $form->addElement('submit', 'send');

        return $form;
    }

    /**
     * Retrieves the type of the field.
     *
     * @param string $field The field.
     *
     * @return string
     */
    private function _getType($field)
    {
        if ($this->_dataSource->isSequence()) {
            $pk = $this->_dataSource->getPk();
            foreach ((array) $pk as $k) {
                $pkField = array_search($k, $this->_fieldToColumnMap);
                if ($pkField == $field) {
                    return 'hidden';
                }
            }
        }

        // TODO : If there is a better fit than 'text' use that
        return 'text';
    }
}