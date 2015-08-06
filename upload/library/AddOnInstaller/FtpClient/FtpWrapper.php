<?php
/*
 * This file is part of the `nicolab/php-ftp-client` package.
 *
 * (c) Nicolas Tallefourtane <dev@nicolab.net>
 *
 * For the full copyright and license information, please view the FtpClient-LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Nicolas Tallefourtane http://nicolab.net
 */

/**
 * Wrap the PHP FTP functions
 *
 * @author Nicolas Tallefourtane <dev@nicolab.net>
 */
class AddOnInstaller_FtpClient_FtpWrapper
{
    /**
     * The connection with the server
     *
     * @var resource
     */
    protected $conn;

    /**
     * Constructor.
     *
     * @param resource &$connection The FTP (or SSL-FTP) connection (takes by reference).
     */
    public function __construct(&$connection)
    {
        $this->conn = &$connection;
    }

    /**
     * Forward the method call to FTP functions
     *
     * @param  string $function
     * @param  array  $arguments
     *
     * @return mixed
     * @throws AddOnInstaller_FtpClient_FtpException When the function is not valid
     */
    public function __call($function, array $arguments)
    {
        $function = 'ftp_' . $function;

        if (function_exists($function))
        {
            array_unshift($arguments, $this->conn);

            return call_user_func_array($function, $arguments);
        }

        throw new AddOnInstaller_FtpClient_FtpException("{$function} is not a valid FTP function");
    }

    /**
     * Opens a FTP connection
     *
     * @param  string $host
     * @param  int    $port
     * @param  int    $timeout
     *
     * @return resource
     */
    public function connect($host, $port = 21, $timeout = 90)
    {
        return ftp_connect($host, $port, $timeout);
    }

    /**
     * Opens a Secure SSL-FTP connection
     *
     * @param  string $host
     * @param  int    $port
     * @param  int    $timeout
     *
     * @return resource
     */
    public function ssl_connect($host, $port = 21, $timeout = 90)
    {
        return ftp_ssl_connect($host, $port, $timeout);
    }
}
