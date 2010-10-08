<?

abstract class BaseModel {

    protected $db;

    private $isNew = false;

    protected $errors = Array();

    protected $hasErrors = false;

    public function __construct($db) {
        $this->db = $db;
    }

    protected function save() {
        if ($this->isValid() === false) {
            return false;
        }
        
        if ($this->getIsNew()) {
            return $this->insert();
        } else {
            return $this->update();
        }
    }

    public function getIsNew() {
        return $this->isNew;
    }

    public function setIsNew($isNew) {
        $this->isNew = $isNew;
    }

    public function hasErrors() {
        return $this->hasErrors;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function setErrors($errors) {
        $this->errors = $errors;
    }
}