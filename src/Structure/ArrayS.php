<?php
/**
 * @author Enric Florit
 * @date 13/7/15
 */

namespace Structure;


class ArrayS extends Structure {
    protected $format;
    protected $countStrict = true;

    public function __construct($data = null, $null = false) {
        parent::__construct("array", $data, $null);
    }

    /**
     * @param array|string[] $format
     * @throws \Exception
     */
    public function setFormat($format) {
        if (is_array($format)) {
            $this->format = $format;
        } else if (is_string($format)) {
            if ($format === "array") {
                $this->format = $format;
                return;
            }

            // /^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]$/ -> class name, provided by php
            $class = '[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*';

            $types = '/^(' .$class. '|\*)?\[\]$/';
            $typesN = '/^(' .$class. '|\*)?\[(\d+)\]$/';
            if (preg_match($types, $format)) {
                $this->format = str_replace("[]", "", $format);
            } else if (preg_match($typesN, $format)) {
                $split = explode("[", $format);
                $type = $split[0];
                $n = str_replace("]", "", $split[1]);

                $this->format = array_fill(0, $n, $type);
            }

            if ($this->format === "") $this->format = "*";
        }
    }

    /**
     * @return array
     */
    public function getFormat() {
        return $this->format;
    }

    /**
     * @return boolean
     */
    public function isCountStrict() {
        return $this->countStrict;
    }

    /**
     * @param boolean $countStrict
     */
    public function setCountStrict($countStrict) {
        $this->countStrict = $countStrict;
    }

    public function checkType($data = null) {
        if (!is_null($data)) $this->data = $data;

        return is_array($this->data);
    }

    /**
     * It assumes that $data is an array
     * @param mixed $data
     * @return bool
     * @throws \Exception
     */
    public function checkFormat($data = null) {
        if (!is_null($data)) $this->data = $data;

        if ($this->format === "array") {
            return is_array($this->data);
        }

        if (is_string($this->format)) {
            foreach ($this->data as $value) {
                $valid = $this->checkValue($value, $this->format);
                if (!$valid) return false;
            }
            return true;
        }

        if ($this->isCountStrict() && count($this->data) !== count($this->format)) return false;

        $associativeData = ArrayS::isAssociative($this->data);
        $associativeFormat = ArrayS::isAssociative($this->format);

        if ($associativeData && $associativeFormat) {
            foreach ($this->getFormat() as $key=>$value) {
                if (!array_key_exists($key, $this->data)) {
                    $valid = false;
                } else {
                    $valid = $this->checkValue($this->data[$key], $value);
                }
                if (!$valid) return false;
            }
            return true;
        } else if (!$associativeData && !$associativeFormat) {
            for ($i = 0; $i < count($this->data); $i++) {
                $valid = $this->checkValue($this->data[$i], $this->format[$i]);
                if (!$valid) return false;
            }
            return true;
        } else {
            return false;
        }
    }

    public function check($data = null) {
        if ($this->getNull()) {
            return (is_null($this->data) || $this->checkType($data)) && $this->checkFormat($data);
        } else {
            return $this->checkType($data) && $this->checkFormat($data);
        }
    }

    protected function checkValue($data, $format, $applyFormat = false) {
        $numeric = '/^(numeric|float|integer|int)(\(|\[)-?\d+(\.\d+)?,-?\d+(\.\d+)?(\)|\])$/';

        if (is_null($data)) {
            $valid = $this->getNull();
        } else if (is_string($format)) {
            if (preg_match($numeric, $format)) {
                switch ($format[0]) {
                    case "n":
                        $structure = new NumericS();
                        break;
                    case "f":
                        $structure = new FloatS();
                        break;
                    case "i":
                        $structure = new IntegerS();
                        break;
                }
                /** @var NumericS $structure */
                $structure->setRange(preg_replace("/^(numeric|float|integer)/", "", $format));
                if ($applyFormat) {
                    return $structure->format($data);
                } else {
                    $valid = $structure->check($data);
                }
            } else {
                switch ($format) {
                    case "scalar":
                        $structure = new ScalarS();
                        break;
                    case "string":
                    case "str":
                        $structure = new StringS();
                        break;
                    case "numeric":
                        $structure = new NumericS();
                        break;
                    case "integer":
                    case "int":
                        $structure = new IntegerS();
                        break;
                    case "float":
                        $structure = new FloatS();
                        break;
                    case "boolean":
                    case "bool":
                        $structure = new BooleanS();
                        break;
                    case "array":
                        $structure = new ArrayS();
                        $structure->setFormat("array");
                        break;
                    case "*":
                    case "any":
                        if ($applyFormat) return $data;
                        $valid = true;
                        break;
                    default:
                        if (class_exists($format)) {
                            if ($applyFormat) return $data;
                            $valid = $data instanceof $format;
                        } else {
                            // maybe $format is a simple array (type[] or type[int])
                            $structure = new ArrayS();
                            $structure->setFormat($format);
                        }
                        break;
                }
                /** @var Structure $structure */
                if (isset($structure)){
                    $structure->setNull($this->getNull());

                    if ($applyFormat) {
                        return $structure->format($data);
                    } else if (!isset($valid)) {
                        try {
                            $valid = $structure->check($data);
                        } catch (\Exception $e) {
                            $valid = false;
                        }
                    }
                } else {
                    $valid = true;
                }
            }
        } else if (is_array($format)) {
            $a = new ArrayS($data, $this->getNull());
            $a->setFormat($format);
            $valid = $a->check();
        } else {
            $valid = true;
        }

        return $valid;
    }

    protected function applyFormat() {
        if (is_string($this->format)) {
            foreach ($this->data as &$value) {
                $value = $this->checkValue($value, $this->format, true);
            }
        }

        if ($this->isCountStrict() && count($this->data) !== count($this->format)) return false;

        $associativeData = ArrayS::isAssociative($this->data);
        $associativeFormat = ArrayS::isAssociative($this->format);

        if ($associativeData && $associativeFormat) {
            foreach ($this->getFormat() as $key=>$value) {
                if (!array_key_exists($key, $this->data)) {
                    throw new \Exception("Non existent key '" . $key . "'");
                } else {
                    $this->data[$key] = $this->checkValue($this->data[$key], $value, true);
                }
            }
            return true;
        } else if (!$associativeData && !$associativeFormat) {
            for ($i = 0; $i < count($this->format); $i++) {
                $this->data[$i] = $this->checkValue($this->data[$i], $this->format[$i], true);
            }
        } else {
            if ($associativeData) {
                throw new \Exception("Error to trying to format an associative array to sequential");
            } else {
                throw new \Exception("Error to trying to format a sequential array to associative");
            }
        }
    }

    public function format($data = null, $format = null) {
        if (!is_null($data)) $this->setData($data);
        if (!is_null($format)) $this->setFormat($format);

        if (!is_array($this->data)) {
            throw new \Exception("Array format only available for arrays");
        }

        $this->applyFormat();
        return $this->data;
    }

    /**
     * Source: http://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential
     * @param array $data
     * @return bool
     */
    public static function isAssociative($data) {
        return array_keys($data) !== range(0, count($data) - 1);
    }
}