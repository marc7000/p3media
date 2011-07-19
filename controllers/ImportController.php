<?php

Yii::import('p3media.extensions.jquery-file-upload.*');

class ImportController extends Controller {

	public $breadcrumbs = array(
		'P3 Media' => array('/p3media')
	);

	public function  init() {
		parent::init();
		$this->getDataDirectory();
	}

	public function actionIndex() {
		$this->render('index');
	}

	public function actionUpload() {
		$this->render('upload');
	}

	public function actionUploadFile() {
		#$script_dir = Yii::app()->basePath.'/data/p3media';
		#$script_dir_url = Yii::app()->baseUrl;
		$options = array(
			'upload_dir' => $this->getDataDirectory(true).DIRECTORY_SEPARATOR,
			#'upload_url' => $script_dir_url . '/files/',
			#'thumbnails_dir' => $script_dir . '/thumbnails/',
			#'thumbnails_url' => $script_dir_url . '/thumbnails/',
			#'thumbnail_max_width' => 80,
			#'thumbnail_max_height' => 80,
			'field_name' => 'file'
		);
		$upload_handler = new EFileUploadHandler($options);

		switch ($_SERVER['REQUEST_METHOD']) {
			case 'HEAD':
			case 'GET':
				$result = $upload_handler->get();
				break;
			case 'POST':
				$result = $upload_handler->post();
				break;
			#case 'DELETE':
			#	$upload_handler->delete();
			#	break;
			default:
				header('HTTP/1.0 405 Method Not Allowed');
		}

		$this->createMedia($result->name, $this->getDataDirectory(false).DIRECTORY_SEPARATOR.$result->name);

		echo CJSON::encode($result);
	}

	public function actionScan() {
		$dir = Yii::getPathOfAlias($this->module->importAlias);
		$files = array();
		foreach (scandir($dir, 0) AS $fileName) {
			$filePath = $dir . DIRECTORY_SEPARATOR . $fileName;
			if (substr($fileName, 0, 1) == ".")
				continue;
			if (is_dir($filePath))
				continue;
			#$md5 = md5_file($filePath);
			$files[] = new CAttributeCollection(array(
					'name' => $fileName,
					'path' => $filePath,
					'id' => md5($fileName),
				));
		}
		$this->render('scan', array('files' => $files));
	}

	public function actionCheck() {
		$warnings = null;
		$result = null;
		$message = null;

		$fileName = $_GET['fileName'];
		$filePath = realpath(Yii::getPathOfAlias($this->module->importAlias) . DIRECTORY_SEPARATOR . $fileName);
		if (is_file($filePath) && strstr($filePath, Yii::getPathOfAlias($this->module->importAlias))) {
			$md5 = md5_file($filePath);
			$result['md5'] = $md5;
			if (Media::model()->findByAttributes(array('md5' => $md5)) !== null) {
				$message .= $warnings[] = "Identical file exists";
			}
			if (Media::model()->findByAttributes(array('originalName' => $fileName)) !== null) {
				$message .= $warnings[] = "File with same name exists";
			}

			if (count($warnings) == 0)
				$message .= "OK";
		} else {
			$warnings[] = "File not found";
		}

		$result['errors'] = $warnings;
		$result['message'] = $message;
		echo CJSON::encode($result);
	}

	public function actionLocalFile() {

		if ($this->resolveFilePath($_GET['fileName'])) {

			$fileName = $_GET['fileName'];
			$importFilePath = $this->resolveFilePath($_GET['fileName']);

			$dataFilePath = $this->generateFileName($fileName);
			copy($importFilePath, Yii::getPathOfAlias($this->module->dataAlias) . DIRECTORY_SEPARATOR . $dataFilePath);
			
			$this->createMedia($fileName, $dataFilePath);
		} else {
			throw new CHttpException(500, 'File not found');
		}
	}

	private function createMedia($fileName, $filePath){
			$fullFilePath = Yii::getPathOfAlias($this->module->dataAlias) . DIRECTORY_SEPARATOR . $filePath;
			$md5 = md5_file($fullFilePath);
			$getimagesize = getimagesize($fullFilePath);

			$model = new Media;

			$model->title = $this->cleanName($fileName, 32);
			$model->originalName = $fileName;

			$model->type = 1; //Media::TYPE_FILE;
			$model->path = $filePath;
			$model->parent_id = 1;
			$model->md5 = $md5;
			$model->mimeType = exec("file -bI " . escapeshellarg($fullFilePath));
			$model->info = CJSON::encode(getimagesize($fullFilePath));
			$model->size = filesize($fullFilePath);

			if ($model->save()) {
				echo CJSON::encode($model->attributes);
			} else {
				$errorMessage = "";
				foreach ($model->errors AS $attrErrors) {
					$errorMessage .= implode(',', $attrErrors);
				}
				throw new CHttpException(500, $errorMessage);
			}

	}


	private function resolveFilePath($fileName) {
		$filePath = realpath(Yii::getPathOfAlias($this->module->importAlias) . DIRECTORY_SEPARATOR . $fileName);
		if (is_file($filePath) && strstr($filePath, Yii::getPathOfAlias($this->module->importAlias))) {
			return $filePath;
		} else {
			return false;
		}
	}

	private function findMd5($md5) {
		$model = Media::model()->findByAttributes(array('md5' => $md5));
		if ($model === null)
			return false;
		else
			return true;
	}

	private function cleanName($name, $maxLength = 0) {
		$name = preg_replace("/[^.A-Za-z0-9_-]/", "", $name);
		if ($maxLength > 0 && strlen($name) > $maxLength) {
			$name = substr($name, 0, $maxLength / 2 - 2) . ".." . substr($name, strlen($name) - $maxLength / 2 + 1);
		}
		return $name;
	}

	private function generateFileName($fileName){
		$ext = strrchr($fileName,'.');
		return Yii::app()->user->id.DIRECTORY_SEPARATOR.uniqid($fileName."-").$ext;
	}

	private function getDataDirectory($fullPath = true){
		$dataDirectory = Yii::app()->user->id;
		$fullDataDirectory = Yii::getPathOfAlias($this->module->dataAlias).DIRECTORY_SEPARATOR.$dataDirectory;
		if (!is_dir($fullDataDirectory)) {
			mkdir ($fullDataDirectory);
		}

		if ($fullPath === true)
			return $fullDataDirectory;
		else
			return $dataDirectory;
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