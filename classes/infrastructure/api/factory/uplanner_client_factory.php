<?php
/**
 * @package     uPlannerConnect
 * @copyright   Cristian Machado Mosquera <cristian.machado@correounivalle.edu.co>
 * @copyright   Daniel Eduardo Dorado <doradodaniel14@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_uplannerconnect\infrastructure\api\factory;

/**
 * Class uplanner_client_factory
 */
class uplanner_client_factory
{
    /**
     * Create a client type
     *
     * @param string $type
     * @return void
     */
    public function create(string $type) {
        $client = null;
        try {
            $class = uplanner_client_type::get_class($type);
            $client = new $class();
        } catch (\Exception $e) {
            error_log('uplanner_client_factory - create: ' . $e->getMessage() . "\n");
        }

        return $client;
    }
}