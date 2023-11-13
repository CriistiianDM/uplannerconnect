<?php
/**
 * @package     local_uplannerconnect
 * @copyright   Cristian Machado <cristian.machado@correounivalle.edu.co>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

namespace local_uplannerconnect\domain\course\usecases;

use local_uplannerconnect\application\service\data_validator;
use local_uplannerconnect\application\repository\moodle_query_handler;
use local_uplannerconnect\plugin_config\plugin_config;
use local_uplannerconnect\domain\service\transition_endpoint;
use moodle_exception;

/**
 *  Extraer los datos
 */
class course_utils
{
    const TABLE_CATEGORY = 'grade_categories';
    const TABLE_ITEMS = 'grade_items';
    const ITEM_TYPE_CATEGORY = 'category';

    private $validator;
    private $moodle_query_handler;
    private $transition_endpoint;

    /**
     *  Construct
     */
    public function __construct()
    {
        $this->validator = new data_validator();
        $this->moodle_query_handler = new moodle_query_handler();
        $this->transition_endpoint = new transition_endpoint();
    }

    /**
     * Retorna los datos del evento user_graded
     *
     * @return array
     */
    public function resourceUserGraded(array $data) : array
    {
        $dataToSave = [];
        try {
            if (empty($data['dataEvent'])) {
                error_log('No le llego la información del evento user_graded');
                return $arraySend;
            }

            //Traer la información
            $event = $data['dataEvent'];
            $getData = $this->validator->isArrayData($event->get_data());
            $grade = $this->validator->isObjectData($event->get_grade());
            $gradeRecordData = $this->validator->isObjectData($grade->get_record_data());
            $gradeLoadItem = $this->validator->isObjectData($grade->load_grade_item());
            $categoryItem = $this->getInstanceCategoryName($gradeLoadItem);
            $categoryFullName = $this->shortCategoryName($categoryItem); 
            $aproved = $this->getAprovedItem($gradeLoadItem, $grade);

            $queryStudent = $this->validator->verifyQueryResult([
                'data' => $this->moodle_query_handler->extract_data_db([
                    'table' => plugin_config::TABLE_USER_MOODLE,
                    'conditions' => [
                        'id' => $this->validator->isIsset($grade->userid)
                    ]
                ]) 
            ])['result'];
             
            $queryCourse = ($this->validator->verifyQueryResult([                        
                'data' => $this->moodle_query_handler->extract_data_db([
                    'table' => plugin_config::TABLE_COURSE,
                    'conditions' => [
                        'id' => $this->validator->isIsset($grade->grade_item->courseid)
                    ]
                ])
            ]))['result'];

            $timestamp =  $this->validator->isIsset(($gradeLoadItem->timecreated));
            $formattedDateCreated = date('Y-m-d', $timestamp);
            $timestampMod =  $this->validator->isIsset(($gradeLoadItem->timemodified));
            $formattedDateModified = date('Y-m-d', $timestampMod);

            //información a guardar
            $dataToSave = [
                'sectionId' => $this->validator->isIsset($queryCourse->shortname),
                'studentCode' => $this->validator->isIsset($queryStudent->username),
                'evaluationGroupCode' => $this->validator->isIsset($categoryFullName), //Bien
                'evaluationId' => $this->validator->isIsset($gradeLoadItem->id),
                'average' => $this->validator->isIsset($this->getWeight($gradeLoadItem)),
                'isApproved' => $this->validator->isIsset($aproved),
                'value' => $this->validator->isIsset(($getData['other'])['finalgrade']),
                'evaluationName' => $this->validator->isIsset($gradeLoadItem->itemname),
                'date' => $this->validator->isIsset($formattedDateCreated),
                'lastModifiedDate' => $this->validator->isIsset($formattedDateModified),
                'action' => strtoupper($data['dispatch']),
                'transactionId' => $this->validator->isIsset($this->transition_endpoint->getLastRowTransaction($grade->grade_item->courseid)),
            ];
        } catch (moodle_exception $e) {
            error_log('Excepción capturada: '. $e->getMessage(). "\n");
        }
        return $dataToSave;
    }

    /**
     * Retorna los datos del evento grade_item_created
     *
     * @return array
     */
    public function resourceGradeItemCreated(array $data) : array
    {
        $dataToSave = [];
        try {
            if (empty($data['dataEvent'])) {
                error_log('No le llego la información del evento user_graded');
                return $arraySend;
            }

            //Traer la información
            $event = $data['dataEvent'];
            $get_grade_item = $this->validator->isObjectData($event->get_grade_item());
            $dataEvent = $this->validator->isIsset($event->get_data());
            $grade = null;

            if (key_exists('userid', $dataEvent)) {
                $grade = $this->validator->isObjectData($get_grade_item->get_grade($dataEvent['userid'], false));    
            }
            
            //category info
            $itemType = $this->validator->isIsset($get_grade_item->itemtype);
            $itemName = $get_grade_item->itemname;

            if ($itemType === self::ITEM_TYPE_CATEGORY) {
                $iteminstance = $this->validator->isIsset($get_grade_item->iteminstance);
                $dataCategory = $this->getDataCategories($iteminstance);
                $categoryItem = $this->getNameCategoryItem($dataCategory);
                $categoryFullName = $this->shortCategoryName($categoryItem);
                $itemName = $categoryItem.' total';
            } else {
                $categoryItem = $this->getInstanceCategoryName($get_grade_item);
                $categoryFullName = $this->shortCategoryName($categoryItem);
            }
            $weight = $this->validator->isIsset($this->getWeight($get_grade_item)) ?? 0;

            $queryCourse = ($this->validator->verifyQueryResult([                        
                'data' => $this->moodle_query_handler->extract_data_db([
                    'table' => plugin_config::TABLE_COURSE,
                    'conditions' => [
                        'id' => $this->validator->isIsset($get_grade_item->courseid)
                    ]
                ])
            ]))['result'];

            $dataToSave = [
                'sectionId' => $this->validator->isIsset($queryCourse->shortname),
                'evaluationGroupCode' => $this->validator->isIsset($categoryFullName),
                'evaluationGroupName' => $this->validator->isIsset(substr($categoryItem, 0, 50)),
                'evaluationId' => $this->validator->isIsset($get_grade_item->id),
                'evaluationName' => $this->validator->isIsset($itemName),
                'weight' => $weight,
                'action' => strtoupper($data['dispatch']),
                "date" => $this->validator->isIsset(strval($dataEvent['timecreated'])),
                'transactionId' => $this->validator->isIsset($this->transition_endpoint->getLastRowTransaction($get_grade_item->courseid)),
            ];
        } catch (moodle_exception $e) {
            error_log('Excepción capturada: '. $e->getMessage(). "\n");
        }
        return $dataToSave;
    }

    /**
     * Retorna el nombre de la categoria
     * 
     * @param object $gradeItem
     * @return bool
     */
    private function getAprovedItem($gradeItem , $gradesGrades) : bool
    {
        $boolean = false;
        if ($gradeItem->grademax) {
            if ($gradeItem->grademax <= $gradesGrades->finalgrade) {
                $boolean = true;
            }
        }
        return $boolean;
    }

    /**
     * Retorna el nombre de la categoria
     * 
     * @param object $gradeItem
     * @return string
     */
    private function getInstanceCategoryName($gradeItem) : string
    {
        $categoryFullName = 'NIVEL000';
        //validar si existe el metodo
        if (property_exists($gradeItem, 'id')) {
            // Ejecutar la consulta.
            $queryResult = $this->moodle_query_handler->executeQuery(sprintf(
                plugin_config::QUERY_NAME_CATEGORY_GRADE, 
                'mdl_'.self::TABLE_ITEMS, 
                'mdl_'.self::TABLE_CATEGORY, 
                $gradeItem->id
            ));
            // Obtener el primer elemento del resultado utilizando reset()
            $firstResult = reset($queryResult);
            if (isset($firstResult->fullname) && 
                strlen($firstResult->fullname) !== 0 && 
                $firstResult->fullname !== '?')
            {
              // Luego, obtén el valor de 'fullname'
              $categoryFullName = $firstResult->fullname;
            }
        }
        return $categoryFullName;
    }

    /**
     * Retorna 10 caracteres del nombre de la categoria
     * 
     * @param string $categoryFullName
     * @return string
     */
    private function shortCategoryName($categoryFullName) : string
    {
        $sinEspacios = str_replace(' ', '', $categoryFullName);
        $categoryShort = substr($sinEspacios, 0, 10);
        return $categoryShort;
    }

    /**
     * Return weight of category
     * 
     * @param object $gradeItem
     * @return float
     */
    private function getWeight($gradeItem)
    {
        $weight = 0;
        if (property_exists($gradeItem, 'aggregationcoef2')) {
            $weight = $gradeItem->aggregationcoef2;
            if ($gradeItem->aggregationcoef2 === 0 ||
                $gradeItem->aggregationcoef2 === 0.0) {
                $weight = $gradeItem->aggregationcoef;
            }
        }
        return $weight;
    }

    /**
     * Retorna el nombre de la categoria
     * 
     * @param object $gradeItem
     * @return string
     */
    private function getNameCategoryItem($queryResult)
    {
        $nameCategory = 'ISCATEGORY001';
        try {
            if (!empty($queryResult)) {
                if (isset($queryResult->fullname) && 
                    strlen($queryResult->fullname) !== 0 && 
                    $queryResult->fullname !== '?')
                {
                  // Luego, obtén el valor de 'fullname'
                  $nameCategory = $queryResult->fullname;
                }
            }
        } catch (moodle_exception $e) {
            error_log('Excepción capturada: '. $e->getMessage(). "\n");
        }
        return $nameCategory;
    }

    /**
     * Return all data of category
     * 
     */
    private function getDataCategories($idCategory)
    {
        $objectClass = new \stdClass();
        try {
            if (!empty($idCategory)) {
                $queryResult = $this->moodle_query_handler->extract_data_db([
                    'table' => self::TABLE_CATEGORY,
                    'conditions' => [
                        'id' => $idCategory
                    ]
                ]);
                $objectClass = $queryResult;
            }
        } catch (moodle_exception $e) {
            error_log('Excepción capturada: '. $e->getMessage(). "\n");
        }
        return $objectClass;
    }
}