<?php
/**
 * @package     local_uplannerconnect
 * @copyright   Cristian Machado <cristian.machado@correounivalle.edu.co>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

namespace local_uplannerconnect\domain\materials;

use local_uplannerconnect\application\service\data_validator;
use local_uplannerconnect\plugin_config\estruture_types;
use local_uplannerconnect\domain\materials\usecases\material_utils;
use local_uplannerconnect\domain\service\utils;
use moodle_exception;

/**
   * Instancia una entidad de acorde a la funcionalidad que se requiera
*/
class material_extraction_data
{
    private $typeEvent;
    private $validator;
    private $materialUtils;
    private $utils;

    public function __construct()
    {
        $this->typeEvent = [
            'resource_created' => 'resourceCreated'
        ];
        $this->validator = new data_validator();
        $this->materialUtils = new material_utils();
        $this->utils = new utils();
    }

    /**
     * Retorna los datos acorde al evento que se requiera
     *
     * @param array $data
     * @return array
     */
    public function getResource(array $data) : array
    {
      $arraySend = [];  
      try {
            if ($this->validator->verificateKeyArrayBoolean([
               'array_verification' => estruture_types::CREATE_EVENT_DATA,
               'get_data' => $data,
            ])) {
                $typeEvent = $this->typeEvent[$data['typeEvent']];
                $arraySend = $this->$typeEvent($data);
            }
      }
      catch (moodle_exception $e) {
         error_log('Excepción capturada: '. $e->getMessage(). "\n");
      }
      return $arraySend;
    }

    /**
     * Return resource created
     * 
     * @param array $data
     * @return array
     */
    private function resourceCreated(array $data) : array
    {
        return $this->send_data_uplanner([
            'data' => $this->materialUtils->resourceCreatedMaterial($data),
            'typeEvent' => $data['typeEvent'],
        ]);
    }

    /**
     * Data Format send to uPlanner
     * 
     * @param array $data
     * @return array
     */
    private function send_data_uplanner(array $data) : array
    {
        return $this->utils->send_data_uplanner($data);
    }
}