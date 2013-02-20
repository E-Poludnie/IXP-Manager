<?php

namespace Repositories;

use Doctrine\ORM\EntityRepository;

/**
 * Vlan
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class Vlan extends EntityRepository
{
    /**
     * The cache key for all VLAN objects
     * @var string The cache key for all VLAN objects
     */
    const ALL_CACHE_KEY = 'inex_vlans';
    
    
    /**
     * Constant to represent normal and private VLANs
     * @var int Constant to represent normal and private VLANs
     */
    const TYPE_ALL     = 0;
    
    /**
     * Constant to represent normal VLANs only
     * @var int Constant to represent normal VLANs ony
     */
    const TYPE_NORMAL  = 1;
    
    /**
     * Constant to represent private VLANs only
     * @var int Constant to represent private VLANs ony
     */
    const TYPE_PRIVATE = 2;
    
    
    /**
     * Return an array of all VLAN objects from the database with caching
     * (and with the option to specify types - returns normal (non-private)
     * VLANs by default.
     *
     * @param $type int The VLAN types to return (see TYPE_ constants).
     * @return array An array of all VLAN objects
     */
    public function getAndCache( $type = self::TYPE_NORMAL )
    {
    	switch( $type )
    	{
    		case self::TYPE_ALL:
    			$where = "";
    			break;
    			
    		case self::TYPE_PRIVATE:
    			$where = "WHERE v.private = 1";
    			break;
    			 
    		default:
    			$where = "WHERE v.private = 0";
    			$type = self::TYPE_NORMAL;        // because we never validated $type
    			break;
    	}
    	
    	return $this->getEntityManager()->createQuery(
                "SELECT v FROM Entities\\Vlan v {$where}"
            )
            ->useResultCache( true, 3600, self::ALL_CACHE_KEY . "_{$type}" )
            ->getResult();
    }
    
    /**
     * Return an array of all VLAN names where the array key is the VLAN id (**not tag**).
     *
     * @param $type int The VLAN types to return (see TYPE_ constants).
     * @return array An array of all VLAN names with the vlan id as the key.
     */
    public function getNames( $type = self::TYPE_NORMAL )
    {
        $vlans = [];
        foreach( $this->getAndCache( $type ) as $a )
            $vlans[ $a->getId() ] = $a->getName();
    
        return $vlans;
    }
    
    /**
     * Return all active, trafficing and external VLAN interfaces on a given VLAN for a given protocol
     * (including customer details)
     *
     * Here's an example of the return:
     *
     *     array(56) {
     *         [0] => array(21) {
     *            ["ipv4enabled"] => bool(true)
     *            ["ipv4hostname"] => string(17) "inex.woodynet.net"
     *            ["ipv6enabled"] => bool(true)
     *            ["ipv6hostname"] => string(20) "inex-v6.woodynet.net"
     *            ....
     *            ["id"] => int(109)
     *                ["Vlan"] => array(5) {
     *                      ["name"] => string(15) "Peering VLAN #1"
     *                      ...
     *                }
     *                ["VirtualInterface"] => array(7) {
     *                    ["id"] => int(39)
     *                    ...
     *                }
     *                ["Customer"] => array(31) {
     *                    ["name"] => string(25) "Packet Clearing House DNS"
     *                   ...
     *                }
     *            }
     *         [1] => array(21) {
     *            ...
     *            }
     *        ...
     *     }
     *
     * @param int $vid The VLAN ID to find interfaces on
     * @param int $protocol The protocol to find interfaces on ( `4` or `6`)
     * @param bool $forceDb Set to true to ignore the cache and force the query to the database
     * @return An array as described above
     * @throws \IXP_Exception Thrown if an invalid protocol is specified
     */
    public function getInterfaces( $vid, $protocol, $forceDb = false )
    {
        if( !in_array( $protocol, [ 4, 6 ] ) )
            throw new \IXP_Exception( 'Invalid protocol' );
        
        $interfaces = $this->getEntityManager()->createQuery(
                "SELECT vli, v, vi, c
         
                FROM \\Entities\\VlanInterface vli
                    LEFT JOIN vli.Vlan v
                    LEFT JOIN vli.VirtualInterface vi
                    LEFT JOIN vi.Customer c
        
                WHERE
                    
                    " . Customer::DQL_CUST_CURRENT . "
                    AND " . Customer::DQL_CUST_TRAFFICING . "
                    AND " . Customer::DQL_CUST_EXTERNAL . "
                    AND c.activepeeringmatrix = 1
                    AND v.id = ?1
                    AND vli.ipv{$protocol}enabled = 1
                    
                GROUP BY vi.Customer
                ORDER BY c.autsys ASC"
            )
            ->setParameter( 1, $vid );
        
        if( !$forceDb )
            $interfaces->useResultCache( true, 3600 );
        
        return $interfaces->getArrayResult();
    }
    
    /**
     * Return all active, trafficing and external customers on a given VLAN for a given protocol
     * (indexed by ASN)
     *
     * Here's an example of the return:
     *
     *     array(56) {
     *         [42] => array(5) {
     *             ["autsys"] => int(42)
     *             ["name"] => string(25) "Packet Clearing House DNS"
     *             ["shortname"] => string(10) "pchanycast"
     *             ["rsclient"] => bool(true)
     *             ["custid"] => int(72)
     *         }
     *         [112] => array(5) {
     *             ["autsys"] => int(112)
     *             ...
     *         }
     *         ...
     *     }
     *
     * @see getInterfaces()
     * @param int $vid The VLAN ID to find interfaces on
     * @param int $protocol The protocol to find interfaces on ( `4` or `6`)
     * @param bool $forceDb Set to true to ignore the cache and force the query to the database
     * @return An array as described above
     * @throws \IXP_Exception Thrown if an invalid protocol is specified
     */
    public function getCustomers( $vid, $protocol, $forceDb = false )
    {
        $key = "vlan_customers_{$vid}_{$protocol}";
        
        if( !$forceDb && ( $custs = \Zend_Registry::get( 'd2cache' )->fetch( $key ) ) )
            return $custs;
        
        $acusts = $this->getInterfaces( $vid, $protocol, $forceDb );
        
        $custs = [];
    
        foreach( $acusts as $c )
        {
            $custs[ $c['VirtualInterface']['Customer']['autsys'] ] = [];
            $custs[ $c['VirtualInterface']['Customer']['autsys'] ]['autsys']    = $c['VirtualInterface']['Customer']['autsys'];
            $custs[ $c['VirtualInterface']['Customer']['autsys'] ]['name']      = $c['VirtualInterface']['Customer']['name'];
            $custs[ $c['VirtualInterface']['Customer']['autsys'] ]['shortname'] = $c['VirtualInterface']['Customer']['shortname'];
            $custs[ $c['VirtualInterface']['Customer']['autsys'] ]['rsclient']  = $c['rsclient'];
            $custs[ $c['VirtualInterface']['Customer']['autsys'] ]['custid']    = $c['VirtualInterface']['Customer']['id'];
        }
        
        \Zend_Registry::get( 'd2cache' )->save( $key, $custs, 86400 );
            
        return $custs;
    }
    
    /**
     * Tempory INEX function until I have a better way of doing this. Used by the
     * `PeeringManagerController`. FIXME.
     */
    public function getPeeringVLANs()
    {
        return $this->getEntityManager()->createQuery(
                "SELECT v
         
                    FROM \\Entities\\Vlan v
            
                    WHERE
            
                        v.number IN ( 10, 12 )
    
                ORDER BY v.number ASC"
            )
            ->getResult();
    }

    
    /**
     * Returns an array of private VLANs with their details and membership.
     *
     * A sample return would be:
     *
     *     [
     *         [8] => [             // vlanId
     *             [vlanid] => 8
     *             [name] => PV-BBnet-HEAnet
     *             [number] => 1300
     *             [members] => [
     *                 [764] => [            // cust ID
     *                     [id] => 764
     *                     [name] => CustA
     *                     [vintid] => 169   // virtual interface ID
     *                 ]
     *                 [60] => [
     *                     [id] => 60
     *                     [name] => CustB
     *                     [vintid] => 212
     *                 ]
     *             ]
     *         ]
     *         [....]
     *         [....]
     *     ]
     *
     * @return array
     */
    public function getPrivateVlanDetails()
    {
    	$vlans = $this->getEntityManager()->createQuery(
    			"SELECT vli, v, vi, pi, sp, s, l, cab, c
     
                FROM \\Entities\\Vlan v
                    LEFT JOIN v.VlanInterfaces vli
                    LEFT JOIN vli.VirtualInterface vi
                    LEFT JOIN vi.Customer c
    				LEFT JOIN vi.PhysicalInterfaces pi
    				LEFT JOIN pi.SwitchPort sp
    				LEFT JOIN sp.Switcher s
    				LEFT JOIN s.Cabinet cab
    				LEFT JOIN cab.Location l
    
                WHERE

    				v.private = 1
    
    			ORDER BY v.number ASC"
    	    )
    	    ->getArrayResult();
    	
    	if( !$vlans || !count( $vlans ) )
    		return [];
    	
    	$pvs = [];
    	
    	foreach( $vlans as $v )
    	{
    		$pvs[ $v['id'] ]['vlanid']   = $v['id'];
    		$pvs[ $v['id'] ]['name']     = $v['name'];
    		$pvs[ $v['id'] ]['number']   = $v['number'];
    		$pvs[ $v['id'] ]['members']  = [];
    			
    		foreach( $v['VlanInterfaces'] as $vli )
    		{
    			$pvs[ $v['id'] ]['members'][ $vli['VirtualInterface']['Customer']['id'] ] = [];
    			$pvs[ $v['id'] ]['members'][ $vli['VirtualInterface']['Customer']['id'] ]['id']     = $vli['VirtualInterface']['Customer']['id'];
    			$pvs[ $v['id'] ]['members'][ $vli['VirtualInterface']['Customer']['id'] ]['name']   = $vli['VirtualInterface']['Customer']['name'];
    			$pvs[ $v['id'] ]['members'][ $vli['VirtualInterface']['Customer']['id'] ]['vintid'] = $vli['VirtualInterface']['id'];
    			
    			$pvs[ $v['id'] ]['infra'] = $vli['VirtualInterface']['PhysicalInterfaces'][0]['SwitchPort']['Switcher']['infrastructure'];
    			
    			$pvs[ $v['id'] ]['members'][ $vli['VirtualInterface']['Customer']['id'] ]['location']
    				= $vli['VirtualInterface']['PhysicalInterfaces'][0]['SwitchPort']['Switcher']['Cabinet']['Location']['name'];
    			
    			$pvs[ $v['id'] ]['members'][ $vli['VirtualInterface']['Customer']['id'] ]['switch']
    				= $vli['VirtualInterface']['PhysicalInterfaces'][0]['SwitchPort']['Switcher']['name'];
    		}
    	}
    	
    	return $pvs;
    }
    
}
