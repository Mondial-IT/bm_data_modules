/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
 */
Drupal.behaviors.bm_xlsx = {
  attach: function (context, settings) {
console.log(document.getElementById("panel1-wrapper"),'in iframe?')
        const iframe =document.getElementById("excel-upload-and-sheet-select-form")
        if(iframe){
          console.log('iframe height',iframe.contentWindow.document.body.scrollHeight + "px")
          iframe.style.height = iframe.contentWindow.document.body.scrollHeight + "px";
        }

    console.log('%c bm_xlsx: %c bm_xlsx.js v11.13.1 is running Ok  ', BM.log.color1, BM.log.color2);
  }
}
