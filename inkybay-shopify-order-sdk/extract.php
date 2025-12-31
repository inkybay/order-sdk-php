<?php


class Extract
{
    private $orderData='';
    private $orderDir='';
    
    public function __construct($dir,$data,$itemFlag)
    {        
        $this->orderData = json_decode($data);
        $this->orderDir = $dir;

        //echo '<pre>';
        //print_r($this->orderData);
        //echo '</pre>';

        // Save json files
        if(isset($this->orderData->order->items) && sizeof($this->orderData->order->items)>0){
            $order_id = $this->orderData->order->id;
            $order_dir = $this->orderDir.'/'.$order_id;
            $item_dir = ($itemFlag=='added')?$order_dir:($order_dir.'/'.$itemFlag);
            if(!is_dir($order_dir))mkdir($order_dir);
            if(!is_dir($item_dir))mkdir($item_dir);
            $json_dir = $item_dir.'/'.(($itemFlag=='added')?'order.json':($itemFlag.'.json'));
            file_put_contents($json_dir, $data);
        }

    }


    public function run(){


        $order_id = $this->orderData->order->id;        
        $items = $this->orderData->order->items;

        $order_dir = $this->orderDir.'/'.$order_id;
        if(!is_dir($order_dir))mkdir($order_dir);
        
        foreach($items as $item){

            $item_dir = $order_dir.'/'.$item->id;

            if(isset($item->product))$this->generateProductCsv($item->product,$item->id,$item_dir);
            if(isset($item->product) && isset($item->product->sides))$this->downloadDesignFiles($item->product,$item->id,$item_dir);            
            if(isset($item->productOption))$this->generateOptionCsv($item->productOption,$item->id,$item_dir);
        }

        if(isset($this->orderData->order->shipping_address))$this->shipingCsv($this->orderData->order->shipping_address,$order_dir);

        
    }


    private function generateProductCsv($productD,$itemId,$itemDir){

        if(!is_dir($itemDir))mkdir($itemDir);

        $filename = $itemDir.'/product.csv';
        $fp = fopen($filename, 'w');
        // Header row
        fputcsv($fp, ['Product ID', 'Name', 'SKU', 'Sizes', 'Total Quantity']);

        // Loop and write
        
        $ad = $productD;
        $sizes_str = '';
        if(isset($ad->sizes) && isset($ad->sizes->sizes)){
            foreach($ad->sizes->sizes as $ni=>$sz){
                $sizes_str.= (($ni>0)?' AND ':'').$sz->type.' '.$sz->typeName.' '.$sz->sizeTitle.' : '.$sz->sizeQty;
            }
        }

        fputcsv($fp, [
            $ad->id ?? '',
            $ad->name ?? '',
            $ad->sku ?? '',
            $sizes_str ?? '',
            $ad->quantity ?? ''
        ]);


        fclose($fp);

        
        if(isset($productD->sides)){

            $filename = $itemDir.'/design.csv';
            $fp = fopen($filename, 'w');

            fputcsv($fp, ['Product Sides', 'Design Area Size', 'Designs', 'Quatity']);
            foreach($productD->sides as $side){


                $size_str = '';
                if(isset($side->designArea) && isset($side->designArea->size)){
                    $szu = $side->designArea->size;
                    $size_str .= $this->sizeFlatten($szu);
                }

                $designs_str = '';
                if(isset($side->design)){
                    foreach($side->design as $design){
                        switch($design->type){
                            case 'TEXT': $designs_str .= 'Text : '.$design->src.', Printing: '.$design->printingType.', Size :'.$this->sizeFlatten($design->size).PHP_EOL;break;
                            default : $designs_str .= 'Clipart : '.$design->name.', Printing: '.$design->printingType.', Size :'.$this->sizeFlatten($design->size).PHP_EOL;break;
                        }                        
                    }
                    if(isset($side->nameNumber) && $side->nameNumber!=[]){
                        $designs_str .= 'Name & Number : '.PHP_EOL.$this->generateNameNumber($side->nameNumber);
                    }
                }

                fputcsv($fp, [          
                $side->name ?? '',
                $size_str ?? '',
                $designs_str ?? '',
                $sizes_str ?? ''                
            ]);
            }

            fclose($fp);
        }

    }


    private function downloadDesignFiles($productD,$itemId,$itemDir){
        foreach($productD->sides as $side){
            if(isset($side->files)){
                foreach($side->files as $files){
                    $flName = strrev(explode('/',strrev($files->src))[0]);
                    $this->download($files->src,$itemDir.'/'.$flName);
                }
            }
        }
    }

    private function sizeFlatten($szu){        
        return '('.$szu->cm->width.' CM X '.$szu->cm->height.' CM '.') OR ('.$szu->inch->width.' INCH X '.$szu->inch->height.' INCH '.')';
    }

    private function generateNameNumber($nn) {
        $nn_str = '';
        foreach ($nn as $nmn) {
            $nn_str.= $nmn->sizeType.' '.$nmn->sizeTypeName.' '.$nmn->sizeTitle.' : Name('.$nmn->name.'), Number('.$nmn->name.')'.PHP_EOL;
        }
        return $nn_str;
    }


    private function generateOptionCsv($optionD,$itemId,$itemDir){

        if(!is_dir($itemDir))mkdir($itemDir);

    
        $filename = $itemDir.'/options.csv';
        $fp = fopen($filename, 'w');
        // Header row
        fputcsv($fp, ['Title', 'Label', 'Data', 'Price']);

        // Loop and write
        foreach ($optionD->options as $ad) {
            fputcsv($fp, [                
                $ad->title ?? '',
                $ad->value->label ?? '',
                $ad->value->data ?? '',
                $ad->price ?? ''
            ]);
        }

        fclose($fp);


    }

    private function shipingCsv($shipingD,$orderDir){

        if(!is_dir($orderDir))mkdir($orderDir);

    
        $filename = $orderDir.'/shipping.csv';
        $fp = fopen($filename, 'w');
        // Header row
        fputcsv($fp, ['Shipping Address', '']);

        // Loop and write
        foreach ($shipingD as $key=>$ad) {
            if($ad){
                fputcsv($fp, [                
                    $key ?? '',
                    $ad ?? ''                    
                ]);
            }
        }

        fclose($fp);
    }


    private function download(string $url, string $dest): bool
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $fp = @fopen($dest, 'wb');
            if (!$fp) return false;

            curl_setopt_array($ch, [
                CURLOPT_FILE           => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 180,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => 'OrderExtract/1.0',
            ]);
            $ok = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);
            if (!$ok || $code >= 400) {
                @unlink($dest);
                return false;
            }
            return true;
        }

        $ctx = stream_context_create([
            'http' => ['timeout' => 60, 'follow_location' => 1],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) return false;
        return file_put_contents($dest, $data) !== false;
    }
    
}



?>