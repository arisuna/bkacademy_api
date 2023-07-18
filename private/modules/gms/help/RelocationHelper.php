<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/16
 * Time: 10:50
 */

namespace Reloday\Gms\Help;

class RelocationHelper
{

    static $relocation;
    static $property;
    static $assignment;
    static $assignee;
    static $relocationServiceCompany;

    /**
     * @param $relocation
     * @param $property
     */
    public static function __getRelocationPropertyDepositAmount($relocation, $property = null)
    {
        if (!$property) {
            $property = $relocation->getSelectedProperty();
        }

        if ($property) {
            return $property->getDepositAmount() . '#' . $property->getDepositCurrency();
        }
    }

    /**
     * @param $relocation
     * @param $property
     * @return string
     */
    public static function __getRelocationPropertyRentAmount($relocation, $property = null)
    {
        if (!$property) {
            $property = $relocation->getSelectedProperty();
        }

        if ($property) {
            return $property->getRentAmount() . '#' . $property->getRentCurrency();
        }
    }

    /**
     * @param $relocation
     * @param $property
     * @return mixed
     */
    public static function __getRelocationPropertyRentPeriod($relocation, $property = null)
    {
        if (!$property) {
            $property = $relocation->getSelectedProperty();
        }

        if ($property) {
            return $property->getRentPeriod();
        }
    }

    /**
     * @param $relocation
     * @param null $property
     * @return null
     */
    public static function __getRelocationPropertyAgentId($relocation, $property = null)
    {
        if (!$property) {
            $property = $relocation->getSelectedProperty();
        }

        if ($property) {
            return $property->getAgent() ? $property->getAgent()->getId() : null;
        }
    }

    /**
     * @param $relocation
     * @param null $property
     * @return null
     */
    public static function __getRelocationPropertyLandlordId($relocation, $property = null)
    {
        if (!$property) {
            $property = $relocation->getSelectedProperty();
        }

        if ($property) {
            return $property->getLandlord() ? $property->getLandlord()->getId() : null;
        }
    }

    /**
     * @param $relocation
     * @param null $assignment
     */
    public static function __getRelocationDestinationCity($relocation, $assignment = null)
    {
        if (!$assignment) {
            $assignment = $relocation->getAssignment();
        }

        if ($assignment) {
            return $assignment->getAssignmentDestination()->getDestinationCity();
        }
    }

    /**
     * @param $relocation
     * @param null $assignment
     */
    public static function __getRelocationDestinationHrName($relocation, $assignment = null)
    {
        if (!$assignment) {
            $assignment = $relocation->getAssignment();
        }
        if ($assignment) {
            if ($assignment->getAssignmentDestination()->getHrOwnerProfile()) {
                return $assignment->getAssignmentDestination()->getHrOwnerProfile()->getName();
            }
        }
    }

    /**
     * @param $relocation
     * @param null $assignment
     */
    public static function __getRelocationDestinationJobTitle($relocation, $assignment = null)
    {
        if (!$assignment) {
            $assignment = $relocation->getAssignment();
        }
        if ($assignment) {
            if ($assignment->getAssignmentDestination()) {
                return $assignment->getAssignmentDestination()->getDestinationJobTitle();
            }
        }
    }

    /**
     * @param $relocation
     * @param null $assignment
     */
    public static function __getRelocationServiceDependantPassportNumber($relocation, $relocationServiceCompany = null)
    {
        if ($relocationServiceCompany->getDependantId() > 0) {
            $dependant = $relocationServiceCompany->getDependant();
            return $dependant ? $dependant->getPassportNumber() : $relocation->getEmployee()->getPassportNumber();
        } else {
            return $relocation->getEmployee()->getPassportNumber();
        }
    }

    /**
     * @param $relocation
     * @param null $assignment
     */
    public static function __getRelocationServiceDependantPassportExpiryDate($relocation, $relocationServiceCompany = null)
    {
        if ($relocationServiceCompany->getDependantId() > 0) {
            $dependant = $relocationServiceCompany->getDependant();
            return $dependant && $dependant->getPassportExpiryDate() ? strtotime($dependant->getPassportExpiryDate()) : null;
        } else {
            return $relocation->getEmployee()->getPassportExpiryDate() != '' ? strtotime($relocation->getEmployee()->getPassportExpiryDate()) : null;
        }
    }

    /**
     * @param $relocation
     * @param null $assignment
     */
    public static function __getRelocationEmployeePassportNumber($relocation, $assignee = null)
    {
        if ($assignee) {
            return $assignee->getPassportNumber();
        } else {
            return $relocation->getEmployee()->getPassportNumber();
        }
    }

    /**
     * @param $relocation
     * @param null $assignment
     */
    public static function __getRelocationEmployeePassportExpiryDate($relocation, $assignee = null)
    {
        if ($assignee) {
            return $assignee->getPassportExpiryDate() != '' ? strtotime($assignee->getPassportExpiryDate()) : null;
        } else {
            return $relocation->getEmployee()->getPassportExpiryDate() != '' ? strtotime($relocation->getEmployee()->getPassportExpiryDate()) : null;
        }
    }

    /**
     * @param $relocation
     * @param null $assignment
     */
    public static function __getRelocationEmployeeVisaName($relocation, $assignee = null)
    {
        if ($assignee) {
            return $assignee->getFirstname() . " " . $assignee->getLastname();
        } else {
            return $relocation->getEmployee()->getFirstname() . " " . $relocation->getEmployee()->getLastname();
        }
    }

    /**
     * @param $relocation
     * @param null $assignment
     */
    public static function __getRelocationServiceDependantVisaName($relocation, $relocationServiceCompany = null)
    {
        if ($relocationServiceCompany->getDependantId() > 0) {
            $dependant = $relocationServiceCompany->getDependant();
            return $dependant ? $dependant->getFirstname() . " " . $dependant->getLastname() : "";
        } else {
            return $relocation->getEmployee()->getFirstname() . " " . $relocation->getEmployee()->getLastname();
        }
    }

    /**
     * @param $relocation
     * @param null $assignment
     */
    public static function __getRelocationEmployeeDependantCount($relocation, $assignment = null)
    {
        if (!$assignment) {
            $assignment = $relocation->getAssignment();
        }
        if ($assignment) {
            return $assignment->countDependants();
        }
    }

    /**
     * @param $relocation
     * @param null $assignment
     * @return mixed
     */
    public static function __getRelocationDestinationBusinessUnit($relocation, $assignment = null)
    {
        if (!$assignment) {
            $assignment = $relocation->getAssignment();
        }
        if ($assignment) {
            return $assignment->getAssignmentDestination()->getDestinationBusinessUnit();
        }
    }


    /**
     * @param $relocation
     * @param null $assignment
     */
    public static function __getRelocationOriginHrName($relocation, $assignment = null)
    {
        if (!$assignment) {
            $assignment = $relocation->getAssignment();
        }
        if ($assignment) {
            if ($assignment->getAssignmentBasic()) {
                return $assignment->getAssignmentBasic()->getHomeHrName();
            }
        }
    }

    /**
     * @param $relocation
     * @param $assignee
     */
    public static function __getRelocationEmployeeDriverLicenceNumber($relocation, $assignee = null)
    {
        if (!$assignee) {
            $assignee = $relocation->getEmployee();
        }
        return $assignee->getDrivingLicenceNumber();
    }

    /**
     * @param $relocation
     * @param $assignee
     */
    public static function __getRelocationEmployeeDriverLicenceExpiryDate($relocation, $assignee = null)
    {
        if (!$assignee) {
            $assignee = $relocation->getEmployee();
        }
        return $assignee->getDrivingLicenceExpiryDate() != '' ? strtotime($assignee->getDrivingLicenceExpiryDate()) : null;
    }

    /**
     * @param $relocation
     * @param $assignee
     */
    public static function __getRelocationHomeSearchPropertyName($relocation, $property = null)
    {
        if (!$property) {
            $property = $relocation->getSelectedProperty();
        }

        if ($property) {
            return $property->getName();
        }
    }

    /**
     * @param $relocation
     * @param $assignee
     */
    public static function __getRelocationHomeSearchPropertyUuid($relocation, $property = null)
    {
        if (!$property) {
            $property = $relocation->getSelectedProperty();
        }

        if ($property) {
            return $property->getUuid();
        }
    }

    /**
     * @param $relocation
     * @param $assignee
     */
    public static function __getRelocationHomeSearchPropertyAddress($relocation, $property = null)
    {
        if (!$property) {
            $property = $relocation->getSelectedProperty();
        }

        if ($property) {
            return $property->getAddress1();
        }
    }

    /**
     * @param $relocation
     * @param $relocationServiceCompany
     * @return false|int
     */
    public static function __getRelocationServicePartnerName($relocation, $relocationServiceCompany)
    {
        $dependant = $relocationServiceCompany->getDependant();
        if ($dependant) {
            return $dependant->getFirstname() . " " . $dependant->getLastname();
        }
    }

    /**
     * @param $relocation
     * @param $relocationServiceCompany
     * @return false|int
     */
    public static function __getRelocationServicePartnerPhone($relocation, $relocationServiceCompany)
    {
        $dependant = $relocationServiceCompany->getDependant();
        if ($dependant) {
            $value = $dependant->getHomePhone();
            if ($value == null) $value = $dependant->getWorkPhone();
            if ($value == null) $value = $dependant->getMobilePhone();
            return $value;
        }
    }

    /**
     * @param $relocation
     * @param $relocationServiceCompany
     * @return false|int
     */
    public static function __getRelocationServicePartnerBirthDate($relocation, $relocationServiceCompany)
    {
        $dependant = $relocationServiceCompany->getDependant();
        if ($dependant && $dependant->getBirthDate() != '') {
            return strtotime($dependant->getBirthDate());
        }
    }

    /**
     * @param $relocation
     * @param $relocationServiceCompany
     * @return string
     */
    public static function __getRelocationServiceDependantName($relocation, $relocationServiceCompany)
    {
        $dependant = $relocationServiceCompany->getDependant();
        if ($dependant) {
            return $dependant->getFirstname() . " " . $dependant->getLastname();
        }
    }
}