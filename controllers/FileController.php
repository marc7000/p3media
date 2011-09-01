<?php

class FileController extends Controller
{
	public function actionIndex()
	{
		#$this->render('index');
		if (!$_GET['id']) {
			throw new CHttpException(404, 'No file specified.');
		} else {
			$model = P3Media::model()->findByPk($_GET['id']);
			$filename = Yii::getPathOfAlias($this->module->dataAlias).DIRECTORY_SEPARATOR.$model->path;
			if (!is_file($filename)) {
				throw new CHttpException(404, 'File not found.');
			} else {
				header('Content-type: '.$model->mimeType);
				readfile($filename);
				exit;
			}
			
		}
	}

	// -----------------------------------------------------------
	// Uncomment the following methods and override them if needed
	/*
	public function filters()
	{
		// return the filter configuration for this controller, e.g.:
		return array(
			'inlineFilterName',
			array(
				'class'=>'path.to.FilterClass',
				'propertyName'=>'propertyValue',
			),
		);
	}

	public function actions()
	{
		// return external action classes, e.g.:
		return array(
			'action1'=>'path.to.ActionClass',
			'action2'=>array(
				'class'=>'path.to.AnotherActionClass',
				'propertyName'=>'propertyValue',
			),
		);
	}
	*/
}