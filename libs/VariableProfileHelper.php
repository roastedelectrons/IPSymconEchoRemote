<?php

declare(strict_types=1);

trait VariableProfileHelper
{
    /** register profiles
     *
     *
     * @param $Name
     * @param $Icon
     * @param $Prefix
     * @param $Suffix
     * @param $MinValue
     * @param $MaxValue
     * @param $StepSize
     * @param $Digits
     * @param $Vartype
     */
    protected function RegisterProfile($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits, $Vartype)
    {
        if (IPS_VariableProfileExists($Name)) {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] !== $Vartype) {
                $this->SendDebug('Profile', 'Variable profile type does not match for profile ' . $Name, 0);
            }
        } else {
            IPS_CreateVariableProfile($Name, $Vartype); // 0 boolean, 1 int, 2 float, 3 string
            $this->SendDebug('Variablenprofil angelegt', $Name, 0);
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        if ($Vartype == VARIABLETYPE_FLOAT)
            IPS_SetVariableProfileDigits($Name, $Digits); //  Nachkommastellen
        if ($Vartype == VARIABLETYPE_FLOAT || $Vartype == VARIABLETYPE_INTEGER)
            IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize); // string $ProfilName, float $Minimalwert, float $Maximalwert, float $Schrittweite
        $this->SendDebug(
            'Variablenprofil konfiguriert',
            'Name: ' . $Name . ', Icon: ' . $Icon . ', Prefix: ' . $Prefix . ', $Suffix: ' . $Suffix . ', Digits: ' . $Digits . ', MinValue: '
            . $MinValue . ', MaxValue: ' . $MaxValue . ', StepSize: ' . $StepSize, 0
        );
    }

    /** register profile association
     *
     * @param $Name
     * @param $Icon
     * @param $Prefix
     * @param $Suffix
     * @param $MinValue
     * @param $MaxValue
     * @param $Stepsize
     * @param $Digits
     * @param $Vartype
     * @param $Associations
     */
    protected function RegisterProfileAssociation($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $Stepsize, $Digits, $Vartype, $Associations)
    {
        if (is_array($Associations) && count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        }

        $this->RegisterProfile($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $Stepsize, $Digits, $Vartype);

        if (!is_array($Associations))
            return;

        // Format associations array to match the IPS_GetVariableProfile return format
        $newAssociations = [];
        foreach ($Associations as $Association)
        {
            $newAssociations[] = array(
                'Value' => $Association[0],
                'Name' => $Association[1],
                'Icon' => $Association[2],
                'Color' => $Association[3]
            );
        }

        $currentAssociations = IPS_GetVariableProfile($Name)['Associations'];

        // only set associations if they have changed
        if ($currentAssociations != $newAssociations)
        {

            // remove current Associations
            if ($Vartype !== 0) { // 0 boolean, 1 int, 2 float, 3 string
                foreach ($currentAssociations as $Association) {
                    IPS_SetVariableProfileAssociation($Name, $Association['Value'], '', '', -1);
                }
            }

            // set new Associations
            foreach ($newAssociations as $Association) {
                // bool IPS_SetVariableProfileAssociation ( string $ProfilName, float $Wert, string $Name, string $Icon, integer $Farbe 
                IPS_SetVariableProfileAssociation($Name, $Association['Value'], $Association['Name'], $Association['Icon'], $Association['Color']);
            }
        }

    }  

    protected function UnregisterProfile(string $Name)
    {
        if (!IPS_VariableProfileExists($Name)) {
            return;
        }
        foreach (IPS_GetVariableList() as $VarID) {
            if (IPS_GetParent($VarID) == $this->InstanceID) {
                continue;
            }
            if (IPS_GetVariable($VarID)['VariableCustomProfile'] == $Name) {
                return;
            }
            if (IPS_GetVariable($VarID)['VariableProfile'] == $Name) {
                return;
            }
        }
        IPS_DeleteVariableProfile($Name);
    }
}