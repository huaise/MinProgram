//加载实例
var app = getApp();
Page({
  data:{
    userInfo:{},
    list:[
            { 
              list_tool:[
                    {
                              img:"/image/photo.png",
                              name:"相册(现在是相册)",
                              url:"../image/image"
                    },
                     {
                              img:"/image/sc_2.png",
                              name:"收藏(现在是上传下载)", 
                              url:"../upload/upload"
                    },
                     {
                              img:"/image/money.png",
                              name:"钱包(现在是播放器)",
                              url:"../voice/voice"
                    },
                     {
                              img:"/image/card.png",
                              name:"卡包(现在是音乐)",
                              url:"../audio/audio"

                    }
                ]
            },
            {
              list_tool:[
                        {
                           img:"/image/bq_2.png",
                           name:"表情(现在是地图)",
                           url:"../map/map"
                }
              ]
            },
            {
              list_tool:[
                          {
                             img:"/image/setting.png",
                             name:"设置(获取系统信息)",
                             url:"../system_data/system_data"
                    
                          }
              ]
            }
      ]
  },
  onLoad: function () {
       var that = this
      //调用应用实例的方法获取全局数据
        app.getUserInfo(function(userInfo){
      //更新数据
      that.setData({
        userInfo:userInfo
      })
    })
  },
  goPage:function(event){ 
    //新窗口打开
    var tourl = event.currentTarget.dataset.url
    if(tourl != ''){
      wx.navigateTo({
            url: event.currentTarget.dataset.url
        })
      }
    }
})