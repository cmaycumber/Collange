package edu.ucf.cop4331.Collange.imagify.filters.color;

import edu.ucf.cop4331.Collange.imagify.Pixel;
import edu.ucf.cop4331.Collange.imagify.RGBTransition;

public class BlueTransition extends RGBTransition {
    @Override
    protected Pixel filterPixel(Pixel pxl, int width, int height){
        if(pxl != null) {
            pxl.setGreen(0);
            pxl.setRed(0);
        }
        return pxl;
    }
}
