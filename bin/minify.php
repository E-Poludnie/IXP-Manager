#!/usr/bin/env php
<?php
 /**
  * Copyright (c) 2010 - 2012 Open Source Solutions Limited <http://www.opensolutions.ie/>
  * All rights reserved.
  * 
  * JS and CSS Minifier
  * Released under the BSD License.
  * 
  * Copyright (c) 2011, Open Source Solutions Limited, Dublin, Ireland <http://www.opensolutions.ie>.
  * Contact: Barry O'Donovan <barry@opensolutions.ie> +353 86 801 7669
  *
  * All rights reserved.
  *
  * Redistribution and use in source and binary forms, with or without modification, are permitted 
  * provided that the following conditions are met:
  *
  *  - Redistributions of source code must retain the above copyright notice, this list of 
  *    conditions and the following disclaimer.
  *  - Redistributions in binary form must reproduce the above copyright notice, this list 
  *    of conditions and the following disclaimer in the documentation and/or other materials 
  *    provided with the distribution.
  *    
  * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS 
  * OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF 
  * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL 
  * THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, 
  * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE 
  * GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND 
  * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING 
  * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED 
  * OF THE POSSIBILITY OF SUCH DAMAGE.
  */
                  
defined( 'APPLICATION_PATH' ) || define( 'APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application' ) );

$whatToCompress = 'all';

if( in_array( 'css', $argv ) && !in_array( 'js', $argv ) )
    $whatToCompress = 'css';

if( in_array( 'js', $argv ) && !in_array( 'css', $argv ) )
    $whatToCompress = 'js';

$version = false;
foreach( $argv as $i => $v )
{
    if( $v == '--version' )
    {
        $version = $argv[$i+1];
        break;
    }
}

if( in_array( $whatToCompress, array( 'all', 'js' ) ) )
{
    print "\n\nMinifying 'public/js':\n\n";

    $files = glob( APPLICATION_PATH . '/../public/js/[0-9][0-9][0-9]-*.js' );
    sort( $files, SORT_STRING );

    $numFiles = sizeof( $files );
    $count = 0;
    $jshdr = '';

    foreach( $files as $oneFileName )
    {
        $count++;

        print "    [{$count}] " . basename( $oneFileName ) . " => min." . basename( $oneFileName ) . "\n";

        exec(   "java -jar " . APPLICATION_PATH . "/../bin/compiler.jar --compilation_level WHITESPACE_ONLY --warning_level QUIET" .
                " --js {$oneFileName} --js_output_file " . APPLICATION_PATH . "/../public/js/min." . basename( $oneFileName )
        );
        
        $jshdr .= "    <script type=\"text/javascript\" src=\"{genUrl}/js/" . basename( $oneFileName ) . "\"></script>\n";
    }

    $mergedJs = '';

    print "\n    Combining...";
    foreach( $files as $fileName )
        $mergedJs .= file_get_contents( APPLICATION_PATH . "/../public/js/min." . basename( $fileName) );

    if( $version )
        file_put_contents( APPLICATION_PATH . "/../public/js/min.bundle-v{$version}.js", $mergedJs );
    else
        file_put_contents( APPLICATION_PATH . "/../public/js/min.bundle.js", $mergedJs );

    file_put_contents( APPLICATION_PATH . "/views/header-js.tpl", $jshdr );
    
    print " done\n\n";
}

if( in_array( $whatToCompress, array( 'all', 'css' ) ) )
{

    print "\nMinifying 'public/css':\n";

    $files = glob( APPLICATION_PATH . '/../public/css/[0-9][0-9][0-9]-*.css' );
    sort( $files, SORT_STRING );

    $numFiles = sizeof( $files );
    $count = 0;
    $csshdr = '';

    foreach( $files as $oneFileName )
    {
        $count++;

        print "    [{$count}] " . basename( $oneFileName ) . " => min." . basename( $oneFileName ) . "\n";

        exec( "java -jar " . APPLICATION_PATH . "/../bin/yuicompressor.jar {$oneFileName} -o " . APPLICATION_PATH . "/../public/css/min." . basename( $oneFileName ) . " -v --charset utf-8" );
    
        $csshdr .= "    <link rel=\"stylesheet\" type=\"text/css\" href=\"{genUrl}/css/" . basename( $oneFileName ) . "\" />\n";
    }

    $mergedCss = '';

    print "\n    Combining...";
    foreach( $files as $fileName )
        $mergedCss .= file_get_contents( APPLICATION_PATH . "/../public/css/min." . basename( $fileName ) );

    if( $version )
        file_put_contents( APPLICATION_PATH . "/../public/css/min.bundle-v{$version}.css", $mergedCss );
    else
        file_put_contents( APPLICATION_PATH . '/../public/css/min.bundle.css', $mergedCss );

    file_put_contents( APPLICATION_PATH . "/views/header-css.tpl", $csshdr );
    
    print ' done\n\n';
}

print "\n\n";

if( $version )
{
    echo "****** VERSION NUMBER WAS SPECIFIED - DON'T FORGET TO UPDATE HEADER FILES!! ******\n\n";
}