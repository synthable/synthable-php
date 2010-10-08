<?

// Check if the right arguments are supplied
if ($argc == 0) {
    echo <<<EOF
Usage: $argv[0] /path/to/schema.xml [-b|-m]

	-b	-- Only generate the BaseModel classes
	-m	-- Generate generic Model classes extending the BaseModel class
EOF;
}

/**
 * Get the path to the model schema and load it into the model gen.
 * Remove the first two elements to process any switches provided.
 * Generate the model.
 */
$filePath = $argv[1];
$mg = new ModelGen($filePath);

array_shift($argv);
array_shift($argv);

foreach($argv as $arg) {
    switch($arg) {
        case "-b":
            $mg->baseOnly = true;
            break;
        case "-m":
            $mg->baseOnly = false;
            break;
        default:
            $mg->baseOnly = true;
            break;
    }
}

$mg->generate();

/**
 * Synthable-PHP framework's model generation class.
 * 
 * @author jason
 */
class ModelGen {

    /**
     * Holds the raw XML
     * @var SimpleXMLElement
     */
    private $xml;

    /**
     * Holds the raw field values
     * @var Array
     */
    private $rawFields = Array();

    /**
     * Holds the formatted field values
     * @var unknown_type
     */
    private $fields = Array();

    /**
     * 
     * @var unknown_type
     */
    public $baseOnly = true;

    public function __construct($file) {
        $this->xml = simplexml_load_file($file);
    }

    public function generate() {
        foreach($this->xml->database->table_structure as $table) {
            $name = $this->toCamelCase($table->attributes()->name);
            
            foreach($table->field as $field) {
                $this->rawFields[] = (string) $field->attributes()->Field;
                $this->fields[] = (string) $this->toCamelCase($field->attributes()->Field);
            }
            
            if ($this->baseOnly === true) {
                $this->generateBaseModel($name);
            } else {
                $this->generateBaseModel($name);
                $this->generateModel($name);
            }
            
            $this->rawFields = Array();
            $this->fields = Array();
        }
    }

    private function generateBaseModel($name) {
        $class = "Base" . ucfirst($name);
        
        $fp = fopen($class . ".model.php", "w");
        $content = <<<EOF
<?

class $class extends BaseModel {

EOF;
        foreach($this->fields as $field) {
            $content .= "    protected $$field;\n";
        }
        $content .= <<<EOF

    public function __construct (\$id = null) {
        parent::__construct(App::\$dbo);

        if(\$id === null) {
            \$this->setIsNew(true);
            return true;
        } else {
            return \$this->load(\$id);
        }
    }

    public function load (\$id) {
        \$sql = "SELECT * FROM \$this->table WHERE id = :id";

        \$query = \$this->db->prepare(\$sql);
        \$query->bindParam(":id", \$id);
        \$result = \$query->execute();

        if(\$result === true) {
            \$c = \$query->fetchObject();


EOF;
        foreach($this->fields as $index => $field) {
            $content .= "            \$this->set" . ucfirst($field) . "(\$c->" . $this->rawFields[$index] . ");\n";
        }
        
        $content .= <<<EOF
        }

        return \$result;
    }

    protected function insert() {
        \$sql = "INSERT INTO \$this->table VALUES(
            null,

EOF;
        
        foreach($this->fields as $index => $field) {
            if ($field == "id") {
                continue;
            }
            $content .= "            :" . $this->rawFields[$index] . ",\n";
        }
        $content = rtrim($content, ",\n");
        
        $content .= <<<EOF

        )";
        \$query = \$this->db->prepare(\$sql);

EOF;
        
        foreach($this->fields as $index => $field) {
            if ($field == "id") {
                continue;
            }
            $content .= "        \$query->bindParam(\":" . $this->rawFields[$index] . "\", \$this->get" . ucfirst($field) . "());\n";
        }
        
        $content .= <<<EOF
        \$result = \$query->execute();

        if(\$result === true) {
            \$this->id = \$this->db->lastInsertId();
        }

        return \$result;
    }

    protected function update() {
        \$sql = "UPDATE \$this->table SET

EOF;
        
        foreach($this->fields as $index => $field) {
            $rf = $this->rawFields[$index];
            $content .= "            $rf = :$rf,\n";
        }
        $content = rtrim($content, ",\n");
        
        $content .= <<<EOF

        WHERE id = :id";
        \$query = \$this->db->prepare(\$sql);

EOF;
        
        foreach($this->fields as $index => $field) {
            $content .= "        \$query->bindParam(\":" . $this->rawFields[$index] . "\", \$this->get" . ucfirst($field) . "());\n";
        }
        
        $content .= <<<EOF
        \$result = \$query->execute();

        return \$result;
    }

EOF;
        
        foreach($this->fields as $field) {
            $content .= "    public function set" . ucfirst($field) . "(\$value) {\n";
            if ($field == "ipaddress") {
                $content .= "        \$this->$field = sprintf(\"%u\", ip2long(\$value));\n";
            } else {
                $content .= "        \$this->$field = \$value;\n";
            }
            $content .= "    }\n";
        }
        
        $content .= "\n";
        
        foreach($this->fields as $field) {
            $content .= "    public function get" . ucfirst($field) . "() {\n";
            if ($field == "ipaddress") {
                $content .= "        return long2ip(\$this->$field);\n";
            } else {
                $content .= "        return \$this->$field;\n";
            }
            $content .= "    }\n";
        }
        
        $content .= <<<EOF

}
EOF;
        
        fwrite($fp, $content, strlen($content));
        fclose($fp);
    }

    /**
     * Generate the model that will be used to modify the behaviour
     * 
     * @param string $name
     */
    public function generateModel($name) {
        $class = ucfirst($name);
        $singular = ( substr($class, - 1) == "s" ) ? substr($class, 0, - 1) : $class;
        
        $fp = fopen($class . ".model.php", "w");
        $content = <<<EOF
<?

class $class extends Base$class {

    protected \$validationRules = Array(

EOF;
        
        foreach($this->fields as $index => $field) {
            $content .= "        '$field' => Array(\n        ),\n";
        }
        $content = rtrim($content, ",\n");
        
        $content .= <<<EOF

    );

    const table = "$name";
    protected \$table = "$name";

    public function __construct (\$id = null) {
        return parent::__construct(\$id);
    }

    public function save() {
        return parent::save();
    }

    public function delete() {
        return parent::delete();
    }
}

class $singular extends $class {}
EOF;
        
        fwrite($fp, $content, strlen($content));
        fclose($fp);
    }

    /**
     * Return the value converted to camel case
     * 
     * @param string $value
     */
    private function toCamelCase($value) {
        $words = explode("_", $value);
        
        $count = count($words);
        if ($count > 1) {
            $formatted = $words[0];
            for($x = 1; $x != $count; $x++) {
                $formatted .= ucfirst($words[$x]);
            }
        } else {
            $formatted = $words[0];
        }
        
        return $formatted;
    }
}
