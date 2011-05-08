<?

abstract class BaseModel {

    protected $db;

    private $isNew = false;

    protected $errors = Array();

    protected $hasErrors = false;

    public function __construct($id, $db) {
        $this->db = $db;

        /**
         * If the id passed in is null, create a new object.
         * Otherwise hydrate the object from the database
         */
        if($id === null) {
            $this->setIsNew(true);
            return true;
        } else {
            return $this->load($id);
        }
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