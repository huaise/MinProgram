Page({
  data:{
        markers:[
                  {
                     latitude: 23.099994,
                      longitude: 113.324520,
                      name: 'T.I.T 创意园',
                      desc: '我现在的位置'
          }
        ],
        convers:[
                  {
                     latitude: 23.099794,
                    longitude: 113.324520,
                    iconPath: '/image/photo.png',
                    rotate: 10
        },
        {
                    latitude: 23.099298,
                    longitude: 113.324129,
                    iconPath: '/image/photo.png',
                    rotate: 90
        }
    ]

  },
  onLoad:function(options){
    // 页面初始化 options为页面跳转所带来的参数
  }
})